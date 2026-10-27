<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'starts_at'    => 'datetime',
        'ends_at'      => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function barber()  { return $this->belongsTo(Barber::class); }
    public function service() { return $this->belongsTo(Service::class); }
}
