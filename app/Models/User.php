<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\Audited;



class User extends Authenticatable
{

    use Audited;
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'barber_id',
        'active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function barber()
    {
        return $this->belongsTo(Barber::class)->withTrashed();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isBarber(): bool
    {
        return $this->role === 'barbero';
    }

    public function isReception(): bool
    {
        return $this->role === 'recepcion';
    }

    public function auditEntity(): string { return 'usuario'; }
public function auditName(): string { return "{$this->name} ({$this->email})"; }
public function auditFields(): array
{
    return [
        'name' => ['Nombre', 'text'], 'email' => ['Correo', 'text'], 'role' => ['Rol', 'text'],
        'barber_id' => ['Barbero vinculado', 'barber'], 'active' => ['Activo', 'bool'],
        'password' => ['Clave', 'secret'],
    ];
}
}