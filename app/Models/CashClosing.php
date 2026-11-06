<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
 use App\Models\Concerns\Audited;

class CashClosing extends Model
{
    use Audited;
    protected $guarded = [];

    protected $casts = ['date' => 'date'];

    public function closer() { return $this->belongsTo(User::class, 'closed_by'); }

    public function auditEntity(): string { return 'cierre de caja'; }
public function auditName(): string { return 'del ' . $this->date->format('d/m/Y'); }
public function auditFields(): array
{
    return [
        'opening_cash' => ['Caja inicial', 'money'], 'counted_cash' => ['Efectivo contado', 'money'],
        'difference' => ['Diferencia', 'money'],
    ];
}
}