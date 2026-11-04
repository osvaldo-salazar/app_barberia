<?php

namespace App\Http\Controllers;

use App\Models\{Appointment, Expense, WeekPayout};
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WeekController extends Controller
{
    public function show(Request $request)
    {
        return view('semana.show', ['d' => $this->data($this->ref($request))]);
    }

    public function pdf(Request $request)
    {
        $d = $this->data($this->ref($request));

        return Pdf::loadView('pdf.week', ['d' => $d])->setPaper('a4')
            ->download('semana-' . $d['start']->toDateString() . '.pdf');
    }

    /** Excel (CSV con separador ; y tildes correctas) */
    public function csv(Request $request)
    {
        $d    = $this->data($this->ref($request));
        $name = 'semana-' . $d['start']->toDateString() . '.csv';

        return response()->streamDownload(function () use ($d) {
            $out  = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // para que Excel lea bien las tildes

            // Evita que un texto que empiece con = + - @ se ejecute como fórmula
            $safe = fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
            $put  = fn (array $row) => fputcsv($out, array_map($safe, $row), ';', '"', '\\');

            $put(['Reporte semanal', config('app.name')]);
            $put(['Semana', $d['start']->format('d/m/Y') . ' al ' . $d['end']->format('d/m/Y')]);
            $put([]);

            $put(['Barbero', 'Cortes', 'Producido', 'Comisión', 'Propinas', 'Total a pagar', 'Estado']);
            foreach ($d['barbers'] as $b) {
                $p = $b['payout'];
                $put([$b['name'], $b['cuts'], $b['total'], $b['commission'], $b['tips'], $b['to_pay'],
                      $p ? 'Pagado (' . $p->method . ') el ' . $p->paid_on->format('d/m/Y') : 'Pendiente']);
            }
            $put([]);

            $put(['Día', 'Cortes', 'Producido', 'Propinas']);
            foreach ($d['days'] as $day) {
                $put([ucfirst($day['date']->translatedFormat('l d/m')), $day['cuts'], $day['total'], $day['tips']]);
            }
            $put([]);

            $put(['Servicio', 'Cantidad', 'Monto']);
            foreach ($d['byService'] as $r) { $put([$r['name'], $r['count'], $r['amount']]); }
            $put([]);

            $put(['Método de pago', 'Cortes', 'Monto con propina']);
            foreach (['efectivo' => 'Efectivo', 'sinpe' => 'SINPE', 'tarjeta' => 'Tarjeta'] as $k => $label) {
                $put([$label, $d['byMethod'][$k]['count'], $d['byMethod'][$k]['amount']]);
            }
            $put([]);

            $put(['Gasto', 'Categoría', 'Fecha', 'Monto']);
            foreach ($d['expenses'] as $e) {
                $put([$e->concept, \App\Models\Expense::CATEGORIES[$e->category] ?? $e->category, $e->date->format('d/m/Y'), $e->amount]);
            }
            $put([]);

            $put(['Parte del jefe', $d['totals']['owner']]);
            $put(['Gastos del local', $d['expTotal']]);
            $put(['Utilidad del jefe', $d['profit']]);

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pay(Request $request)
    {
        $data = $request->validate([
            'week'      => ['required', 'date_format:Y-m-d'],
            'barber_id' => ['required', 'exists:barbers,id'],
            'method'    => ['required', 'in:efectivo,sinpe'],
        ], [
            'method.required' => 'Elige cómo se le pagó al barbero.',
        ]);

        $start = Carbon::createFromFormat('Y-m-d', $data['week'])->startOfWeek(Carbon::SUNDAY)->startOfDay();
        $end   = $start->copy()->addDays(6)->endOfDay();

        if ($start->gt(today())) {
            return back()->withErrors(['week' => 'Esa semana todavía no empieza.']);
        }

        if (WeekPayout::where('week_start', $start->toDateString())->where('barber_id', $data['barber_id'])->exists()) {
            return back()->withErrors(['barber_id' => 'Ese barbero ya está marcado como pagado en esa semana.']);
        }

        $q      = Appointment::where('status', 'completada')->where('barber_id', $data['barber_id'])->whereBetween('starts_at', [$start, $end]);
        $amount = (int) (clone $q)->sum('barber_amount') + (int) (clone $q)->sum('tip');

        if ($amount <= 0) {
            return back()->withErrors(['barber_id' => 'No hay nada que pagarle a ese barbero esta semana.']);
        }

        WeekPayout::create([
            'week_start' => $start->toDateString(),
            'barber_id'  => $data['barber_id'],
            'amount'     => $amount,
            'method'     => $data['method'],
            'paid_on'    => today()->toDateString(),
            'user_id'    => auth()->id(),
        ]);

        return redirect()->route('semana', ['fecha' => $start->toDateString()])->with('ok', 'Pago registrado.');
    }

    public function unpay(WeekPayout $payout)
    {
        $week = $payout->week_start->toDateString();
        $payout->delete();

        return redirect()->route('semana', ['fecha' => $week])->with('ok', 'Pago deshecho.');
    }

    /* ---------- Helpers ---------- */

    private function ref(Request $request): Carbon
    {
        $request->validate(['fecha' => 'nullable|date_format:Y-m-d']);

        return $request->filled('fecha') ? Carbon::createFromFormat('Y-m-d', $request->fecha) : now();
    }

    /** Todo lo de la semana (domingo a sábado) */
    private function data(Carbon $ref): array
    {
        $start = $ref->copy()->startOfWeek(Carbon::SUNDAY)->startOfDay();
        $end   = $start->copy()->addDays(6)->endOfDay();
        $payAt = $start->copy()->addDays(6)->setTime(19, 0);

        $done = Appointment::with(['barber', 'service'])
            ->where('status', 'completada')
            ->whereBetween('starts_at', [$start, $end])
            ->get();

        $payouts = WeekPayout::where('week_start', $start->toDateString())->get()->keyBy('barber_id');

        $barbers = $done->groupBy('barber_id')->map(function ($g, $id) use ($payouts) {
            $toPay = (int) $g->sum('barber_amount') + (int) $g->sum('tip');
            $p     = $payouts->get($id);

            return [
                'id'         => (int) $id,
                'name'       => $g->first()->barber->name,
                'cuts'       => $g->count(),
                'total'      => (int) $g->sum('price'),
                'commission' => (int) $g->sum('barber_amount'),
                'tips'       => (int) $g->sum('tip'),
                'owner'      => (int) $g->sum('owner_amount'),
                'to_pay'     => $toPay,
                'payout'     => $p,
                'changed'    => $p && (int) $p->amount !== $toPay,
            ];
        })->sortByDesc('total')->values();

        $byMethod = [];
        foreach (['efectivo', 'sinpe', 'tarjeta'] as $m) {
            $g = $done->where('payment_method', $m);
            $byMethod[$m] = ['count' => $g->count(), 'amount' => (int) ($g->sum('price') + $g->sum('tip'))];
        }

        $byService = $done->groupBy('service_id')->map(fn ($g) => [
            'name' => $g->first()->service->name, 'count' => $g->count(), 'amount' => (int) $g->sum('price'),
        ])->sortByDesc('count')->values();

        $days = collect(range(0, 6))->map(function ($i) use ($start, $done) {
            $day = $start->copy()->addDays($i);
            $g   = $done->filter(fn ($a) => $a->starts_at->isSameDay($day));

            return ['date' => $day, 'cuts' => $g->count(), 'total' => (int) $g->sum('price'), 'tips' => (int) $g->sum('tip')];
        });

        $expenses = Expense::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')->get();
        $expTotal = (int) $expenses->sum('amount');
        $owner    = (int) $done->sum('owner_amount');

        return [
            'start'     => $start,
            'end'       => $end,
            'payAt'     => $payAt,
            'barbers'   => $barbers,
            'days'      => $days,
            'byMethod'  => $byMethod,
            'byService' => $byService,
            'expenses'  => $expenses,
            'expTotal'  => $expTotal,
            'profit'    => $owner - $expTotal,
            'totals'    => [
                'cuts'       => $done->count(),
                'total'      => (int) $done->sum('price'),
                'commission' => (int) $done->sum('barber_amount'),
                'tips'       => (int) $done->sum('tip'),
                'owner'      => $owner,
            ],
            'unclosed'  => Appointment::where('status', 'pendiente')
                ->whereBetween('starts_at', [$start, $end])->where('starts_at', '<', now())->count(),
        ];
    }
}   