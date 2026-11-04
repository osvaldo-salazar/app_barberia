<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashEntry extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date'];
}