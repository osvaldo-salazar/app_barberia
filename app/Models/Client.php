<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Audited;

class Client extends Model
{
    use Audited;

    public const NAME_REGEX = '/^[\pL\pM][\pL\pM\s.\'-]*$/u';
    public const PHONE_REGEX = '/^[2-8]\d{7}$/';

    protected $guarded = [];

    public function auditEntity(): string
    {
        return 'cliente';
    }

    public function auditName(): string
    {
        return $this->full_name;
    }

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
     * Deja solo los 8 dígitos: quita espacios, guiones y el +506.
     */
    public static function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return (strlen($digits) === 11 && str_starts_with($digits, '506'))
            ? substr($digits, 3)
            : $digits;
    }
}