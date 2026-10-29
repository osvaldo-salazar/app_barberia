<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $ref = $request->filled('fecha')
            ? Carbon::parse($request->fecha)
            : now();

        // La semana inicia el domingo y termina el sábado
        $start = $ref->copy()
            ->startOfWeek(Carbon::SUNDAY)
            ->startOfDay();

        $end = $start->copy()
            ->addDays(6)
            ->endOfDay();

        $payDay = $start->copy()
            ->addDays(6)
            ->setTime(19, 0); // Sábado 7 p. m.

        // Resumen semanal por barbero
        $rows = Appointment::with('barber')
            ->where('status', 'completada')
            ->whereBetween('starts_at', [$start, $end])
            ->selectRaw('
                barber_id,
                COUNT(*) as cuts,
                SUM(price) as total,
                SUM(barber_amount) as barber_total,
                SUM(owner_amount) as owner_total,
                SUM(tip) as tips
            ')
            ->groupBy('barber_id')
            ->get();

        // Ingresos semanales agrupados por método de pago
        $byMethod = Appointment::where('status', 'completada')
            ->whereBetween('starts_at', [$start, $end])
            ->selectRaw('
                payment_method,
                SUM(price + COALESCE(tip, 0)) as total
            ')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        return view('comisiones', [
            'rows' => $rows,
            'start' => $start,
            'end' => $end,
            'payDay' => $payDay,
            'prev' => $start->copy()->subWeek()->toDateString(),
            'next' => $start->copy()->addWeek()->toDateString(),

            'byMethod' => $byMethod,

            'totals' => [
                'total' => $rows->sum('total'),
                'barber' => $rows->sum('barber_total'),
                'owner' => $rows->sum('owner_total'),
                'tips' => $rows->sum('tips'),
            ],
        ]);
    }
}