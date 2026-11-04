<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashClosing extends Model
{
    protected $guarded = [];

    protected $casts = ['date' => 'date'];

    public function closer() { return $this->belongsTo(User::class, 'closed_by'); }
}