<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Audited;


class Expense extends Model
{
    use Audited;

    public const CATEGORIES = [
        'alquiler'      => 'Alquiler',
        'servicios'     => 'Luz, agua e internet',
        'productos'     => 'Productos e insumos',
        'mantenimiento' => 'Mantenimiento',
        'publicidad'    => 'Publicidad',
        'otros'         => 'Otros',
    ];

    protected $guarded = [];

    protected $casts = ['date' => 'date', 'from_cash' => 'boolean'];

    public function auditEntity(): string { return 'gasto'; }
public function auditName(): string
{
    return "{$this->concept} · ₡" . number_format($this->amount, 0, ',', '.') . ($this->from_cash ? ' (de la caja)' : '');
}
}