<?php

namespace App\Services;

use App\Models\{Appointment, CashClosing, CashEntry, Expense, WeekPayout};
use Carbon\Carbon;

class CashBook
{
    /** Cifras del día a partir de las citas cobradas */
    public function day(Carbon $date): array
    {
        $done = Appointment::with(['barber', 'service'])
            ->where('status', 'completada')
            ->whereDate('starts_at', $date)
            ->get();

        $byMethod = [];
        foreach (['efectivo', 'sinpe', 'tarjeta'] as $m) {
            $g = $done->where('payment_method', $m);
            $byMethod[$m] = ['count' => $g->count(), 'amount' => (int) ($g->sum('price') + $g->sum('tip'))];
        }
        $none = $done->whereNull('payment_method');
        $byMethod['sin_metodo'] = ['count' => $none->count(), 'amount' => (int) ($none->sum('price') + $none->sum('tip'))];

        $byService = $done->groupBy('service_id')->map(fn ($g) => [
            'name'   => $g->first()->service->name,
            'count'  => $g->count(),
            'amount' => (int) $g->sum('price'),
        ])->sortByDesc('count')->values();

        $byBarber = $done->groupBy('barber_id')->map(fn ($g) => [
            'name'   => $g->first()->barber->name,
            'cuts'   => $g->count(),
            'total'  => (int) $g->sum('price'),
            'barber' => (int) $g->sum('barber_amount'),
            'tips'   => (int) $g->sum('tip'),
            'owner'  => (int) $g->sum('owner_amount'),
        ])->sortByDesc('total')->values();

        $others = Appointment::whereDate('starts_at', $date)
            ->whereIn('status', ['pendiente', 'no_asistio', 'cancelada'])
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return [
            'cuts'      => $done->count(),
            'services'  => (int) $done->sum('price'),
            'tips'      => (int) $done->sum('tip'),
            'byMethod'  => $byMethod,
            'byService' => $byService,
            'byBarber'  => $byBarber,
            'pending'   => (int) ($others['pendiente'] ?? 0),
            'noshow'    => (int) ($others['no_asistio'] ?? 0),
            'canceled'  => (int) ($others['cancelada'] ?? 0),
        ];
    }

    /** Movimientos de efectivo del día que no son cobros: gastos, pagos a barberos, retiros y aportes */
    public function moves(Carbon $date): array
    {
        $d = $date->toDateString();
        $items = collect();

        foreach (Expense::where('date', $d)->where('from_cash', true)->get() as $e) {
            $items->push(['kind' => 'Gasto', 'label' => $e->concept, 'amount' => -$e->amount, 'entry_id' => null]);
        }
        foreach (WeekPayout::with('barber')->where('paid_on', $d)->where('method', 'efectivo')->get() as $p) {
            $items->push(['kind' => 'Pago a barbero', 'label' => $p->barber->name, 'amount' => -$p->amount, 'entry_id' => null]);
        }
        foreach (CashEntry::where('date', $d)->orderBy('id')->get() as $c) {
            $items->push([
                'kind'     => $c->type === 'entrada' ? 'Entrada' : 'Salida',
                'label'    => $c->concept,
                'amount'   => $c->type === 'entrada' ? $c->amount : -$c->amount,
                'entry_id' => $c->id,
            ]);
        }

        return ['items' => $items, 'net' => (int) $items->sum('amount')];
    }

    /** Caja con la que empieza el día: lo que se contó en el último cierre + lo que pasó después */
    public function opening(Carbon $date): int
    {
        $d    = $date->copy()->startOfDay();
        $last = CashClosing::where('date', '<', $d->toDateString())->orderByDesc('date')->first();

        $base = $last ? (int) $last->counted_cash : 0;
        $from = $last ? $last->date->copy()->addDay()->startOfDay() : null;
        $to   = $d->copy()->subDay()->endOfDay();

        if ($from && $from->gt($to)) {
            return $base;
        }

        return $base + $this->net($from, $to);
    }

    /** Efectivo que entró menos el que salió entre dos fechas (días sin cierre) */
    private function net(?Carbon $from, Carbon $to): int
    {
        $sales = Appointment::where('status', 'completada')
            ->where('payment_method', 'efectivo')
            ->where('starts_at', '<=', $to)
            ->when($from, fn ($q) => $q->where('starts_at', '>=', $from))
            ->selectRaw('COALESCE(SUM(price + tip), 0) as t')
            ->value('t');

        $range = fn ($q, string $col) => $q
            ->where($col, '<=', $to->toDateString())
            ->when($from, fn ($w) => $w->where($col, '>=', $from->toDateString()));

        $expenses = $range(Expense::where('from_cash', true), 'date')->sum('amount');
        $payouts  = $range(WeekPayout::where('method', 'efectivo'), 'paid_on')->sum('amount');
        $in       = $range(CashEntry::where('type', 'entrada'), 'date')->sum('amount');
        $out      = $range(CashEntry::where('type', 'salida'), 'date')->sum('amount');

        return (int) $sales + (int) $in - (int) $out - (int) $expenses - (int) $payouts;
    }
}