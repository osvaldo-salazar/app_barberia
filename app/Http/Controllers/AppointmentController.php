<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Service;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use App\Models\TimeBlock;

class AppointmentController extends Controller
{
    const OPEN_HOUR = 10;
    const CLOSE_HOUR = 19;

    public function index(Request $request)
{
    $request->validate([
        'fecha' => 'nullable|date',
        'cliente' => 'nullable|integer',
    ]);

    $prefill = $request->filled('cliente')
        ? Client::withCount([
            'appointments as visits' => fn ($a) => $a->where('status', 'completada'),
        ])->find($request->cliente)
        : null;

    $user = auth()->user();

    abort_if(
        $user->isBarber() && ! $user->barber_id,
        403,
        'Tu usuario no está vinculado a un barbero.'
    );

    $date = $request->filled('fecha')
        ? Carbon::parse($request->fecha)
        : today();

    $appointments = Appointment::with([
        'barber',
        'service',
        'client' => fn ($q) => $q->withCount([
            'appointments as visits' => fn ($a) => $a->where('status', 'completada'),
        ]),
    ])
        ->whereDate('starts_at', $date)
        ->when(
            $user->isBarber(),
            fn ($q) => $q->where('barber_id', $user->barber_id)
        )
        ->orderBy('starts_at')
        ->get();

    // Columnas: barberos activos + los que tengan citas ese día
    // aunque ya estén inactivos o eliminados.
    $columns = Barber::withTrashed()
        ->where(function ($q) use ($appointments) {
            $q->where(
                fn ($w) => $w->whereNull('deleted_at')
                    ->where('active', true)
            )
            ->orWhereIn('id', $appointments->pluck('barber_id'));
        })
        ->when(
            $user->isBarber(),
            fn ($q) => $q->where('id', $user->barber_id)
        )
        ->orderBy('name')
        ->get();

    $done = $appointments->where('status', 'completada');

    $stats = [
        'total' => $appointments->where('status', '!=', 'cancelada')->count(),
        'pending' => $appointments->where('status', 'pendiente')->count(),
        'done' => $done->count(),
        'money' => $user->isBarber()
            ? $done->sum('barber_amount') + $done->sum('tip')
            : $done->sum('price'),
    ];

    // Bloqueos del día, recortados al horario visible (10:00 a 19:00) y en minutos
    $dayStart = $date->copy()->startOfDay();
    $dayEnd   = $date->copy()->endOfDay();

    $blocks = TimeBlock::overlapping($dayStart, $dayEnd)
        ->when($user->isBarber(), fn ($q) => $q->forBarber($user->barber_id))
        ->get()
        ->map(function ($b) use ($dayStart, $dayEnd) {
            $s = (int) $dayStart->diffInMinutes($b->starts_at->max($dayStart));
            $e = (int) $dayStart->diffInMinutes($b->ends_at->min($dayEnd));

            return [
                'barber_id' => $b->barber_id,
                'from'      => max(600, $s),
                'to'        => min(1140, $e),
                'reason'    => $b->reason ?: 'Bloqueado',
            ];
        })
        ->filter(fn ($r) => $r['to'] > $r['from'])
        ->values();

    return view('agenda', [
        'date' => $date,
        'appointments' => $appointments,
        'columns' => $columns,
        'prefill' => $prefill,

        'barbers' => Barber::where('active', true)
            ->when(
                $user->isBarber(),
                fn ($q) => $q->where('id', $user->barber_id)
            )
            ->orderBy('name')
            ->get(),

        'services' => Service::where('active', true)
            ->orderBy('name')
            ->get(),

        'stats' => $stats,
        'blocks' => $blocks,
    ]);
}

    public function store(Request $request)
    {
        $data = $this->resolve($request);

        $appointment = Appointment::create([
            ...$data,
            'status' => 'pendiente',
            'barber_amount' => 0,
            'owner_amount' => 0,
            'tip' => 0,
        ]);

        return redirect()
            ->route('agenda', [
                'fecha' => $appointment->starts_at->toDateString(),
            ])
            ->with('ok', 'Cita registrada correctamente.');
    }

    public function edit(Appointment $appointment)
    {
        $this->own($appointment);

        $appointment->load(['barber', 'service', 'client']);

        $barbers = Barber::where('active', true)
            ->orderBy('name')
            ->get();

        $services = Service::where('active', true)
            ->orderBy('name')
            ->get();

        return view('citas.edit', compact(
            'appointment',
            'barbers',
            'services'
        ));
    }

    public function update(Request $request, Appointment $appointment)
    {
        $this->own($appointment);

        abort_unless(
            $appointment->status === 'pendiente',
            403,
            'Reabre la cita para editarla.'
        );

        $f = $this->resolve($request, $appointment);

        $appointment->update($f);

        return redirect()
            ->route('agenda', [
                'fecha' => $f['starts_at']->toDateString(),
            ])
            ->with('ok', 'Cita actualizada.');
    }

    public function destroy(Appointment $appointment)
    {
        $this->own($appointment);

        abort_unless(
            $appointment->status === 'pendiente',
            403,
            'Reabre la cita para eliminarla.'
        );

        $date = $appointment->starts_at->toDateString();

        $appointment->delete();

        return redirect()
            ->route('agenda', ['fecha' => $date])
            ->with('ok', 'Cita eliminada.');
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        $this->own($appointment);

        $data = $request->validate([
            'status' => 'required|in:pendiente,completada,cancelada,no_asistio',
            'payment_method' => 'required_if:status,completada|nullable|in:efectivo,sinpe,tarjeta',
            'tip' => 'nullable|integer|min:0|max:1000000',
        ], [
            'payment_method.required_if' => 'Elige el método de pago.',
        ]);

        abort_if(
            auth()->user()->isReception()
                && (
                    $data['status'] === 'completada'
                    || $appointment->status === 'completada'
                ),
            403,
            'La recepción no puede cobrar ni reabrir citas.'
        );

        if ($data['status'] === 'completada') {
            $barberAmount = (int) round(
                $appointment->price * $appointment->commission_percent / 100
            );

            $appointment->barber_amount = $barberAmount;
            $appointment->owner_amount = $appointment->price - $barberAmount;
            $appointment->payment_method = $data['payment_method'];
            $appointment->tip = (int) ($data['tip'] ?? 0);
            $appointment->completed_at = now();
        } else {
            $appointment->barber_amount = 0;
            $appointment->owner_amount = 0;
            $appointment->payment_method = null;
            $appointment->tip = 0;
            $appointment->completed_at = null;
        }

        $appointment->status = $data['status'];
        $appointment->save();

        return back()->with('ok', 'Estado actualizado.');
    }

    /**
     * Horas de inicio libres, según la duración del servicio.
     */
    public function availableSlots(Request $request)
    {
        $data = $request->validate([
            'barber_id' => 'required|exists:barbers,id',
            'service_id' => 'required|exists:services,id',
            'date' => 'required|date',
            'exclude' => 'nullable|integer',
        ]);

        $user = auth()->user();

        abort_if(
            $user->isBarber() && $data['barber_id'] != $user->barber_id,
            403
        );

        $dur = (int) Service::findOrFail($data['service_id'])->duration_minutes;
        $day = Carbon::parse($data['date']);

        // Citas del barbero ese día (en minutos desde medianoche).
        $busy = Appointment::where('barber_id', $data['barber_id'])
            ->whereIn('status', ['pendiente', 'completada'])
            ->whereDate('starts_at', $day)
            ->when(
                $data['exclude'] ?? null,
                fn ($q, $id) => $q->where('id', '!=', $id)
            )
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at'])
            ->map(fn ($a) => [
                $a->starts_at->hour * 60 + $a->starts_at->minute,
                $a->ends_at->hour * 60 + $a->ends_at->minute,
            ]);

        $open = self::OPEN_HOUR * 60;
        $close = self::CLOSE_HOUR * 60;
        $cursor = $open;

        // Hoy: no se ofrecen horas que ya pasaron.
        // Se redondea al próximo cuarto de hora.
        if ($day->isToday()) {
            $now = now();

            $cursor = max(
                $open,
                (int) (ceil(($now->hour * 60 + $now->minute) / 15) * 15)
            );
        }

        $slots = [];

        $addGap = function (int $from, int $to) use (&$slots, $dur) {
            for ($t = $from; $t + $dur <= $to; $t += $dur) {
                $slots[] = sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
            }
        };

        foreach ($busy as [$s, $e]) {
            if ($s > $cursor) {
                $addGap($cursor, $s);
            }

            $cursor = max($cursor, $e);
        }

        $addGap($cursor, $close);

        return response()->json(['slots' => $slots]);
    }

    /**
     * Cantidad de citas por día de un mes (para los puntos del calendario).
     */
    public function calendar(Request $request)
    {
        $data = $request->validate([
            'mes' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        $start = Carbon::createFromFormat('Y-m-d', $data['mes'] . '-01')->startOfDay();
        $end = $start->copy()->endOfMonth();
        $user = auth()->user();

        $counts = Appointment::whereBetween('starts_at', [$start, $end])
            ->where('status', '!=', 'cancelada')
            ->when(
                $user->isBarber(),
                fn ($q) => $q->where('barber_id', $user->barber_id)
            )
            ->selectRaw('DATE(starts_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        return response()->json($counts);
    }

    /* ---------- Helpers ---------- */

    /**
     * Un barbero solo puede acceder a sus propias citas.
     */
    private function own(Appointment $appointment): void
    {
        $user = auth()->user();

        abort_if(
            $user->isBarber()
                && (int) $appointment->barber_id !== (int) $user->barber_id,
            403,
            'No tienes permiso para modificar esta cita.'
        );
    }

    /**
     * Valida y arma los datos de la cita.
     * Sirve para crear y editar.
     */
private function resolve(Request $request, ?Appointment $current = null): array
{
    $user = auth()->user();
    if ($user->isBarber()) {
        $request->merge(['barber_id' => $user->barber_id]);
    }

    // Barbero o servicio: no eliminado y activo (o el que ya tiene la cita, al editar)
    $available = fn (string $table, ?int $keep) => Rule::exists($table, 'id')->where(
        fn ($q) => $q->whereNull('deleted_at')
                     ->where(fn ($w) => $w->where('active', 1)->orWhere('id', $keep))
    );

    $rules = [
        'barber_id'  => ['required', $available('barbers', $current?->barber_id)],
        'service_id' => ['required', $available('services', $current?->service_id)],
        'date'       => ['required', 'date_format:Y-m-d'],
        'time'       => ['required', 'date_format:H:i'],
    ];
    $messages = [
        'barber_id.required'  => 'Elige un barbero.',
        'barber_id.exists'    => 'Ese barbero ya no está disponible.',
        'service_id.required' => 'Elige un servicio.',
        'service_id.exists'   => 'Ese servicio ya no está disponible.',
        'date.required'       => 'Elige una fecha.',
        'date.date_format'    => 'La fecha no es válida.',
        'time.required'       => 'Elige una hora.',
        'time.date_format'    => 'La hora no es válida.',
    ];

    $isNew = false;

    if (! $current) {
        $isNew = $request->input('_cmode') === 'new';
        $request->merge(['phone' => Client::normalizePhone($request->phone)]);

        if ($isNew) {
            $nameRule = ['required', 'string', 'max:60', 'regex:' . Client::NAME_REGEX];

            $rules += [
                'client_name'      => $nameRule,
                'client_last_name' => $nameRule,
                'phone'            => [
                    'required',
                    'regex:' . Client::PHONE_REGEX,
                    function ($attribute, $value, $fail) {
                        if ($c = Client::where('phone', $value)->first()) {
                            $fail("Ese teléfono ya está registrado a nombre de {$c->full_name}. Búscalo en el buscador de clientes.");
                        }
                    },
                ],
            ];
            $messages += [
                'client_name.required'      => 'Escribe el nombre del cliente.',
                'client_last_name.required' => 'Escribe el apellido del cliente.',
                'client_name.regex'         => 'El nombre solo puede tener letras.',
                'client_last_name.regex'    => 'El apellido solo puede tener letras.',
                'phone.required'            => 'Escribe el teléfono del cliente.',
            ];
        } else {
            $rules['phone'] = ['required', 'regex:' . Client::PHONE_REGEX, Rule::exists('clients', 'phone')];
            $messages += [
                'phone.required' => 'Busca un cliente o crea uno nuevo con el botón Nuevo.',
                'phone.exists'   => 'Ese cliente no existe. Búscalo de nuevo o crea uno nuevo.',
            ];
        }

        $messages['phone.regex'] = 'El teléfono debe tener 8 dígitos y empezar con 2 a 8.';
    }

    $data = $request->validate($rules, $messages);

    $service = Service::findOrFail($data['service_id']);
    $barber  = Barber::findOrFail($data['barber_id']);

    $start = Carbon::createFromFormat('Y-m-d H:i', $data['date'] . ' ' . $data['time']);
    $end   = $start->copy()->addMinutes($service->duration_minutes);

    // 1) Dentro del horario del local
    $open  = $start->copy()->setTime(self::OPEN_HOUR, 0);
    $close = $start->copy()->setTime(self::CLOSE_HOUR, 0);
    if ($start < $open || $end > $close) {
        throw ValidationException::withMessages(['time' => 'Ese servicio no cabe dentro del horario (10am - 7pm).']);
    }

    // 2) El barbero no puede tener dos citas a la vez
    $barberBusy = Appointment::where('barber_id', $barber->id)
        ->whereIn('status', ['pendiente', 'completada'])
        ->where('starts_at', '<', $end)
        ->where('ends_at', '>', $start)
        ->when($current, fn ($q) => $q->where('id', '!=', $current->id))
        ->exists();

    if ($barberBusy) {
        throw ValidationException::withMessages(['time' => 'Ese barbero ya tiene una cita a esa hora.']);
    }

    // 3) El cliente tampoco puede tener dos citas a la vez (ni con otro barbero)
    $phone = $current ? $current->phone : $data['phone'];

    $clientBusy = Appointment::where('phone', $phone)
        ->whereIn('status', ['pendiente', 'completada'])
        ->where('starts_at', '<', $end)
        ->where('ends_at', '>', $start)
        ->when($current, fn ($q) => $q->where('id', '!=', $current->id))
        ->exists();

    if ($clientBusy) {
        throw ValidationException::withMessages(['time' => 'Este cliente ya tiene otra cita a esa misma hora.']);
    }

    // Al editar, solo se recalcula el precio/porcentaje si cambió el servicio o el barbero
    $price   = ($current && $current->service_id == $service->id) ? $current->price : $service->price;
    $percent = ($current && $current->barber_id == $barber->id) ? $current->commission_percent : $barber->commission_percent;

    // Cliente: se crea o se busca recién ahora, cuando ya pasaron todas las validaciones
    if ($current) {
        $who = [
            'client_id'        => $current->client_id,
            'client_name'      => $current->client_name,
            'client_last_name' => $current->client_last_name,
            'phone'            => $current->phone,
        ];
    } else {
        $client = $isNew
            ? Client::create([
                'name'      => $data['client_name'],
                'last_name' => $data['client_last_name'],
                'phone'     => $data['phone'],
            ])
            : Client::where('phone', $data['phone'])->firstOrFail();

        $who = [
            'client_id'        => $client->id,
            'client_name'      => $client->name,
            'client_last_name' => $client->last_name,
            'phone'            => $client->phone,
        ];
    }

    return $who + [
        'barber_id'          => $barber->id,
        'service_id'         => $service->id,
        'starts_at'          => $start,
        'ends_at'            => $end,
        'price'              => $price,
        'commission_percent' => $percent,
    ];
}
}