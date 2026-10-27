<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $ref = $request->filled('fecha') ? Carbon::parse($request->fecha) : now();

        // La semana inicia el domingo y termina el sábado
        $start = $ref->copy()->startOfWeek(Carbon::SUNDAY)->startOfDay();
        $end   = $start->copy()->addDays(6)->endOfDay();
        $payDay = $start->copy()->addDays(6)->setTime(19, 0); // sábado 7pm

        $rows = Appointment::with('barber')
            ->where('status', 'completada')
            ->whereBetween('starts_at', [$start, $end])
            ->selectRaw('barber_id, COUNT(*) as cuts, SUM(price) as total, SUM(barber_amount) as barber_total, SUM(owner_amount) as owner_total')
            ->groupBy('barber_id')
            ->get();

        return view('comisiones', [
            'rows'   => $rows,
            'start'  => $start,
            'end'    => $end,
            'payDay' => $payDay,
            'prev'   => $start->copy()->subWeek()->toDateString(),
            'next'   => $start->copy()->addWeek()->toDateString(),
            'totals' => [
                'total'  => $rows->sum('total'),
                'barber' => $rows->sum('barber_total'),
                'owner'  => $rows->sum('owner_total'),
            ],
        ]);
    }
}