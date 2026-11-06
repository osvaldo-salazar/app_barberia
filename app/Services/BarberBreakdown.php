<?php

namespace App\Services;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BarberBreakdown
{
    /** Por barbero: totales, servicios y desglose día por día entre dos fechas */
    public function forRange(Carbon $from, Carbon $to): Collection
    {
        $done = Appointment::with(['barber', 'service'])
            ->where('status', 'completada')
            ->whereBetween('starts_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('starts_at')
            ->get();

        return $done->groupBy('barber_id')->map(function ($rows, $id) {
            $services = $rows->groupBy('service_id')->map(fn ($g) => [
                 'name' => $g->first()->service?->name ?? 'Servicio no disponible',                'count'      => $g->count(),
                'amount'     => (int) $g->sum('price'),
                'commission' => (int) $g->sum('barber_amount'),
            ])->sortByDesc('count')->values();

            $days = $rows->groupBy(fn ($a) => $a->starts_at->toDateString())->map(function ($g, $date) {
                $commission = (int) $g->sum('barber_amount');
                $tips       = (int) $g->sum('tip');

                return [
                    'date'       => Carbon::parse($date),
                    'cuts'       => $g->count(),
                    'total'      => (int) $g->sum('price'),
                    'commission' => $commission,
                    'tips'       => $tips,
                    'earn'       => $commission + $tips,
                    'services'   => $g->groupBy('service_id')->map(fn ($s) => [
                    'name' => $s->first()->service?->name ?? 'Servicio no disponible',                        'count'      => $s->count(),
                        'commission' => (int) $s->sum('barber_amount'),
                    ])->sortByDesc('count')->values(),
                ];
            })->sortKeys()->values();

            $commission = (int) $rows->sum('barber_amount');
            $tips       = (int) $rows->sum('tip');

            return [
                'id'         => (int) $id,
                'name' => $rows->first()->barber?->name ?? 'Barbero no disponible',                                 
                'cuts'       => $rows->count(),
                'total'      => (int) $rows->sum('price'),
                'commission' => $commission,
                'tips'       => $tips,
                'owner'      => (int) $rows->sum('owner_amount'),
                'to_pay'     => $commission + $tips,
                'services'   => $services,
                'days'       => $days,
            ];
        })->sortByDesc('total')->values();
    }
}