<?php

namespace App\Http\Controllers;

use App\Models\{Appointment, CashClosing};
use Carbon\Carbon;
use Illuminate\Http\Request;

class CashController extends Controller
{
    public function show(Request $request)
    {
        $request->validate(['fecha' => 'nullable|date_format:Y-m-d']);

        $date = $request->filled('fecha')
            ? Carbon::createFromFormat('Y-m-d', $request->fecha)->startOfDay()
            : today();

        $s       = $this->summary($date);
        $closing = CashClosing::with('closer')->where('date', $date->toDateString())->first();

        // ¿Cambió algo en las citas después de guardar el cierre?
        $stale = $closing && (
            (int) $closing->expected_cash  !== $s['byMethod']['efectivo'] ||
            (int) $closing->expected_sinpe !== $s['byMethod']['sinpe'] ||
            (int) $closing->expected_card  !== $s['byMethod']['tarjeta'] ||
            (int) $closing->tips           !== $s['tips']
        );

        return view('caja.show', compact('date', 's', 'closing', 'stale'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date'         => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'counted_cash' => ['required', 'integer', 'min:0', 'max:100000000'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ], [
            'date.before_or_equal'  => 'No se puede cerrar la caja de un día futuro.',
            'counted_cash.required' => 'Escribe cuánto efectivo contaste.',
            'counted_cash.integer'  => 'El efectivo contado debe ser un número entero, sin decimales.',
        ]);

        $s = $this->summary(Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay());

        CashClosing::updateOrCreate(
            ['date' => $data['date']],
            [
                'expected_cash'  => $s['byMethod']['efectivo'],
                'expected_sinpe' => $s['byMethod']['sinpe'],
                'expected_card'  => $s['byMethod']['tarjeta'],
                'tips'           => $s['tips'],
                'counted_cash'   => $data['counted_cash'],
                'difference'     => $data['counted_cash'] - $s['byMethod']['efectivo'],
                'notes'          => $data['notes'] ?? null,
                'closed_by'      => auth()->id(),
            ]
        );

        return redirect()->route('caja', ['fecha' => $data['date']])->with('ok', 'Cierre de caja guardado.');
    }

    private function summary(Carbon $date): array
    {
        $done = Appointment::with('barber')
            ->where('status', 'completada')
            ->whereDate('starts_at', $date)
            ->get();

        $byMethod = ['efectivo' => 0, 'sinpe' => 0, 'tarjeta' => 0, 'sin_metodo' => 0];
        foreach ($done as $a) {
            $byMethod[$a->payment_method ?: 'sin_metodo'] += $a->price + $a->tip;
        }

        $barbers = $done->groupBy('barber_id')->map(fn ($g) => [
            'name'   => $g->first()->barber->name,
            'cuts'   => $g->count(),
            'total'  => (int) $g->sum('price'),
            'barber' => (int) $g->sum('barber_amount'),
            'tips'   => (int) $g->sum('tip'),
            'owner'  => (int) $g->sum('owner_amount'),
        ])->sortByDesc('total')->values();

        return [
            'byMethod' => $byMethod,
            'services' => (int) $done->sum('price'),
            'tips'     => (int) $done->sum('tip'),
            'count'    => $done->count(),
            'barbers'  => $barbers,
            'pending'  => Appointment::where('status', 'pendiente')->whereDate('starts_at', $date)->count(),
        ];
    }
}