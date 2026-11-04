<?php

namespace App\Http\Controllers;

use App\Models\{Appointment, Barber, Expense, Service};
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $weeks    = in_array((int) $request->semanas, [4, 8, 12], true) ? (int) $request->semanas : 8;
        $thisWeek = now()->startOfWeek(Carbon::SUNDAY)->startOfDay();
        $from     = $thisWeek->copy()->subWeeks($weeks - 1);
        $to       = $thisWeek->copy()->addDays(6)->endOfDay();

        $done = Appointment::where('status', 'completada')
            ->whereBetween('starts_at', [$from, $to])
            ->get(['id', 'barber_id', 'service_id', 'starts_at', 'price', 'tip', 'owner_amount', 'payment_method']);

        $expenses = Expense::whereBetween('date', [$from->toDateString(), $to->toDateString()])->get(['date', 'amount']);

        $wk     = fn ($c) => $c->copy()->startOfWeek(Carbon::SUNDAY)->toDateString();
        $doneBy = $done->groupBy(fn ($a) => $wk($a->starts_at));
        $expBy  = $expenses->groupBy(fn ($e) => $wk($e->date));

        // Semana a semana
        $labels = $produced = $profit = [];
        for ($i = 0; $i < $weeks; $i++) {
            $s   = $from->copy()->addWeeks($i);
            $key = $s->toDateString();
            $p   = $doneBy->get($key, collect());

            $labels[]   = $s->translatedFormat('j M');
            $produced[] = (int) $p->sum('price');
            $profit[]   = (int) $p->sum('owner_amount') - (int) $expBy->get($key, collect())->sum('amount');
        }

        $barberNames  = Barber::withTrashed()->pluck('name', 'id');
        $serviceNames = Service::withTrashed()->pluck('name', 'id');

        $barbers = $done->groupBy('barber_id')->map(fn ($g, $id) => [
            'name' => $barberNames[$id] ?? 'Sin nombre', 'cuts' => $g->count(), 'total' => (int) $g->sum('price'),
        ])->sortByDesc('cuts')->values();

        $services = $done->groupBy('service_id')->map(fn ($g, $id) => [
            'name' => $serviceNames[$id] ?? 'Sin nombre', 'count' => $g->count(),
        ])->sortByDesc('count')->take(8)->values();

        $byHour = $done->groupBy(fn ($a) => $a->starts_at->hour)->map->count();
        $h0     = min(10, $byHour->keys()->min() ?? 10);
        $h1     = max(18, $byHour->keys()->max() ?? 18);
        $hours  = collect(range($h0, $h1))->map(fn ($h) => [
            'label' => Carbon::createFromTime($h)->format('g A'), 'count' => (int) $byHour->get($h, 0),
        ])->values();

        $byDay    = $done->groupBy(fn ($a) => $a->starts_at->dayOfWeek)->map->count();
        $weekdays = collect(['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'])
            ->map(fn ($n, $i) => ['label' => $n, 'count' => (int) $byDay->get($i, 0)])->values();

        $methods = [];
        foreach (['efectivo' => 'Efectivo', 'sinpe' => 'SINPE', 'tarjeta' => 'Tarjeta'] as $k => $label) {
            $methods[] = ['label' => $label, 'amount' => (int) ($done->where('payment_method', $k)->sum('price')
                                                              + $done->where('payment_method', $k)->sum('tip'))];
        }

        $thisKey = $thisWeek->toDateString();
        $lastKey = $thisWeek->copy()->subWeek()->toDateString();
        $now     = (int) $doneBy->get($thisKey, collect())->sum('price');
        $before  = (int) $doneBy->get($lastKey, collect())->sum('price');

        $kpi = [
            'week'    => $now,
            'delta'   => $before > 0 ? round(($now - $before) / $before * 100) : null,
            'profit'  => $profit[$weeks - 1] ?? 0,
            'cuts'    => $done->count(),
            'average' => $done->count() ? (int) round($done->avg('price')) : 0,
        ];

        $data = [
            'weeks'    => ['labels' => $labels, 'produced' => $produced, 'profit' => $profit],
            'barbers'  => $barbers,
            'services' => $services,
            'hours'    => $hours,
            'weekdays' => $weekdays,
            'methods'  => $methods,
        ];

        return view('dashboard', compact('data', 'kpi', 'weeks'));
    }
}