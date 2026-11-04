<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeBlock extends Model
{
    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
        'all_day'   => 'boolean',
    ];

    public function barber() { return $this->belongsTo(Barber::class)->withTrashed(); }

    public function scopeOverlapping($query, $start, $end)
    {
        return $query->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }

    /** Bloqueos de ese barbero + los de toda la barbería */
    public function scopeForBarber($query, $barberId)
    {
        return $query->where(fn ($w) => $w->whereNull('barber_id')->orWhere('barber_id', $barberId));
    }
}