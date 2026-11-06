<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use SoftDeletes;
    use Audited;

    protected $guarded = [];

public function auditEntity(): string { return 'servicio'; }
public function auditName(): string { return $this->name; }
public function auditFields(): array
{
    return [
        'name' => ['Nombre', 'text'], 'price' => ['Precio', 'money'],
        'duration_minutes' => ['Duración (min)', 'text'], 'active' => ['Activo', 'bool'],
    ];
}
}
