<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barber extends Model
{
    protected $guarded = [];
    public function appointments() { return $this->hasMany(Appointment::class); }
}
