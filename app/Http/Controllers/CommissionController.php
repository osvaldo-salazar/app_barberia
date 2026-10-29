<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
   public function index(Request $request)
{
    $user = auth()->user();
    abort_if($user->isBarber() && ! $user->barber_id, 403, 'Tu usuario no está vinculado a un barbero.');

    $ref   = $request->filled('fecha') ? Carbon::parse($request->fecha) : now();
    $start = $ref->copy()->startOfWeek(Carbon::SUNDAY)->startOfDay();
    $end   = $start->copy()->addDays(6)->endOfDay();
    $payDay = $start->copy()->addDays(6)->setTime(19, 0);

    $base = Appointment::where('status', 'completada')
        ->whereBetween('starts_at', [$start, $end])
        ->when($user->isBarber(), fn ($q) => $q->where('barber_id', $user->barber_id));

    $rows = (clone $base)->with('barber')
        ->selectRaw('barber_id, COUNT(*) as cuts, SUM(price) as total, SUM(barber_amount) as barber_total, SUM(owner_amount) as owner_total, SUM(tip) as tips')
        ->groupBy('barber_id')
        ->get();

    $byMethod = (clone $base)
        ->selectRaw('payment_method, SUM(price + tip) as total')
        ->groupBy('payment_method')
        ->pluck('total', 'payment_method');

    return view('comisiones', [
        'rows'     => $rows,
        'byMethod' => $byMethod,
        'start'    => $start,
        'end'      => $end,
        'payDay'   => $payDay,
        'prev'     => $start->copy()->subWeek()->toDateString(),
        'next'     => $start->copy()->addWeek()->toDateString(),
        'totals'   => [
            'total'  => $rows->sum('total'),
            'barber' => $rows->sum('barber_total'),
            'owner'  => $rows->sum('owner_total'),
            'tips'   => $rows->sum('tips'),
        ],
    ]);
}
}