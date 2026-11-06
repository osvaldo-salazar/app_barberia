<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Concerns\Audited;

class ActivityLog extends Model
{
    use Audited;
    
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }

    /** Registra un movimiento. Nunca debe romper la app, así que cualquier falla se reporta y se ignora. */
    public static function note(string $description, string $action = 'security', $subject = null, $user = null): void
    {
        try {
            $user ??= auth()->user();

            static::create([
                'user_id'      => $user?->id,
                'user_name'    => $user?->name,
                'action'       => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id'   => $subject?->getKey(),
                'description'  => Str::limit($description, 1000),
                'ip'           => request()->ip(),
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}