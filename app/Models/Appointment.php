<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Audited;

class Appointment extends Model
{
     use audited;

    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'completed_at' => 'datetime',
        'reminded_at' => 'datetime',
    ];

    public function barber()
    {
        return $this->belongsTo(Barber::class)->withTrashed();
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function auditEntity(): string { return 'cita'; }
    public function auditName(): string
    {
        return "{$this->client_name} {$this->client_last_name} · " . $this->starts_at->translatedFormat('D j M, g:i A');
    }
    public function auditFields(): array
    {
        return [
            'status' => ['Estado', 'status'], 'payment_method' => ['Pago', 'method'],
            'price' => ['Precio', 'money'], 'tip' => ['Propina', 'money'],
            'barber_id' => ['Barbero', 'barber'], 'service_id' => ['Servicio', 'service'],
            'starts_at' => ['Horario', 'datetime'], 'proof_path' => ['Comprobante', 'flag'],
        ];
    }
}
