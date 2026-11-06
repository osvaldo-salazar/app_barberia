<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\Audited;


class Barber extends Model
{
    use SoftDeletes;

    use Audited;

    protected $fillable = [
        'name',
        'commission_percent',
        'active',
    ];

    public function auditEntity(): string { return 'barbero'; }
    public function auditName(): string { return $this->name; }
    public function auditFields(): array
    {
        return [
            'name' => ['Nombre', 'text'], 'commission_percent' => ['Comisión', 'percent'],
            'weekly_goal' => ['Meta semanal', 'text'], 'active' => ['Activo', 'bool'],
        ];
    }
}   