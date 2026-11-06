<?php

namespace App\Models\Concerns;

use App\Models\{ActivityLog, Barber, Service};
use Illuminate\Support\Carbon;

trait Audited
{
    public static function bootAudited(): void
    {
        static::created(fn ($m) => $m->audit('created'));
        static::updated(fn ($m) => $m->audit('updated'));
        static::deleted(fn ($m) => $m->audit('deleted'));
    }

    abstract public function auditEntity(): string;   // 'cita', 'cliente'...
    abstract public function auditName(): string;     // nombre legible del registro

    /** campo => [etiqueta, tipo]. Tipos: text, money, percent, bool, flag, datetime, status, method, barber, service, secret */
    public function auditFields(): array
    {
        return [];
    }

    protected function audit(string $event): void
    {
        // Seeders, migraciones y tinker no se registran
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        $text = match ($event) {
            'created' => 'Creó ' . $this->auditEntity() . ': ' . $this->auditName(),
            'deleted' => 'Eliminó ' . $this->auditEntity() . ': ' . $this->auditName(),
            default   => $this->describeChanges(),
        };

        if ($text) {
            ActivityLog::note($text, $event, $this);
        }
    }

    protected function describeChanges(): ?string
    {
        $fields = $this->auditFields();
        $parts  = [];

        foreach ($this->getChanges() as $attr => $new) {
            if (! isset($fields[$attr])) {
                continue;
            }

            [$label, $type] = $fields[$attr];

            if ($type === 'secret') {
                $parts[] = $label . ' cambiada';
                continue;
            }

            $parts[] = $label . ': ' . $this->auditValue($this->getOriginal($attr), $type) . ' → ' . $this->auditValue($new, $type);
        }

        return $parts
            ? 'Cambió ' . $this->auditEntity() . ' ' . $this->auditName() . ' (' . implode('; ', $parts) . ')'
            : null;
    }

    protected function auditValue($v, string $type): string
    {
        if ($v === null || $v === '') {
            return '—';
        }

        return match ($type) {
            'money'    => ((int) $v < 0 ? '-' : '') . '₡' . number_format(abs((int) $v), 0, ',', '.'),
            'percent'  => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%',
            'bool'     => $v ? 'sí' : 'no',
            'flag'     => 'sí',
            'datetime' => Carbon::parse($v)->translatedFormat('D j M, g:i A'),
            'barber'   => Barber::withTrashed()->find($v)?->name ?? "#{$v}",
            'service'  => Service::withTrashed()->find($v)?->name ?? "#{$v}",
            'status'   => ['pendiente' => 'pendiente', 'completada' => 'cobrada', 'cancelada' => 'cancelada', 'no_asistio' => 'no vino'][$v] ?? $v,
            'method'   => ['efectivo' => 'efectivo', 'sinpe' => 'SINPE', 'tarjeta' => 'tarjeta'][$v] ?? $v,
            default    => (string) $v,
        };
    }
}