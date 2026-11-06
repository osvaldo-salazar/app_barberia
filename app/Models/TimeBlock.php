<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Audited;

class TimeBlock extends Model
{
    use Audited;
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

    public function auditEntity(): string { return 'bloqueo de horario'; }
public function auditName(): string
{
    return ($this->reason ?: 'Bloqueo') . ' · ' . ($this->barber?->name ?? 'toda la barbería') . ' · '
        . $this->starts_at->translatedFormat('D j M, g:i A');
}
}