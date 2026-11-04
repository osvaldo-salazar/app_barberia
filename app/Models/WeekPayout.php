<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeekPayout extends Model
{
    protected $guarded = [];

    protected $casts = ['week_start' => 'date', 'paid_on' => 'date'];

    public function barber() { return $this->belongsTo(Barber::class)->withTrashed(); }
}