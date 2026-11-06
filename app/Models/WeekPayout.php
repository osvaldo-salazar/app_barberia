<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Audited;


class WeekPayout extends Model
{
    use Audited;
    protected $guarded = [];

    protected $casts = ['week_start' => 'date', 'paid_on' => 'date'];

    public function barber() { return $this->belongsTo(Barber::class)->withTrashed(); }
    public function auditEntity(): string { return 'pago a barbero'; }
public function auditName(): string
{
    return ($this->barber?->name ?? 'Barbero') . ' · ₡' . number_format($this->amount, 0, ',', '.')
        . ' por ' . $this->method . ' (semana del ' . $this->week_start->format('d/m') . ')';
}
}