<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    public const NAME_REGEX  = '/^[\pL\pM][\pL\pM\s.\'-]*$/u'; // solo letras, espacios, punto, apóstrofe y guion
    public const PHONE_REGEX = '/^[2-8]\d{7}$/';                // 8 dígitos de Costa Rica

    protected $guarded = [];

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->name} {$this->last_name}";
    }

    public function getPhoneFormattedAttribute(): string
    {
        return substr($this->phone, 0, 4) . '-' . substr($this->phone, 4);
    }

    /**
     * Deja solo los 8 dígitos: quita espacios, guiones y el +506
     */
    public static function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return (strlen($digits) === 11 && str_starts_with($digits, '506'))
            ? substr($digits, 3)
            : $digits;
    }
}