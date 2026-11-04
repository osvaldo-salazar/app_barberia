<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
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
}