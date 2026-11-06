<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
 use App\Models\Concerns\Audited;


class CashEntry extends Model
{
    use Audited;
    protected $guarded = [];

    protected $casts = ['date' => 'date'];

    public function auditEntity(): string { return 'movimiento de caja'; }
public function auditName(): string
{
    return "{$this->type} de ₡" . number_format($this->amount, 0, ',', '.') . " ({$this->concept})";
}
}