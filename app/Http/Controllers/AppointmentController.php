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
use App\Services\ProofStore;

class AppointmentController extends Controller
{
  /** Minutos (desde medianoche) entre los que se puede agendar */
private function dayBounds(): array
{
    return [(int) config('barberia.day_start') * 60, (int) config('barberia.day_end') * 60];
}

private function hm(int $minutes): string
{
    return Carbon::today()->addMinutes($minutes)->format('g:i A');
}

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

   [$winFrom, $winTo] = $this->dayBounds();
    $dayStart = $date->copy()->startOfDay();
    $dayEnd   = $date->copy()->endOfDay();

    // Bloqueos del día, recortados al rango visible y expresados en minutos
    $blocks = TimeBlock::overlapping($dayStart, $dayEnd)
        ->when($user->isBarber(), fn ($q) => $q->forBarber($user->barber_id))
        ->get()
        ->map(function ($b) use ($dayStart, $dayEnd, $winFrom, $winTo) {
            $s = (int) $dayStart->diffInMinutes($b->starts_at->max($dayStart));
            $e = (int) $dayStart->diffInMinutes($b->ends_at->min($dayEnd));

            return [
                'barber_id' => $b->barber_id,
                'from'      => max($winFrom, $s),
                'to'        => min($winTo, $e),
                'reason'    => $b->reason ?: 'Bloqueado',
            ];
        })
        ->filter(fn ($r) => $r['to'] > $r['from'])
        ->values();

        // Metas de la semana actual (domingo a sábado). La recepción no las ve.
$goals = collect();
if (! $user->isReception()) {
    $wStart = today()->startOfWeek(Carbon::SUNDAY);
    $wEnd   = $wStart->copy()->addDays(6)->endOfDay();

    $counts = Appointment::where('status', 'completada')
        ->whereBetween('starts_at', [$wStart, $wEnd])
        ->selectRaw('barber_id, COUNT(*) as c')->groupBy('barber_id')->pluck('c', 'barber_id');

    $goals = Barber::where('active', true)->whereNotNull('weekly_goal')
        ->when($user->isBarber(), fn ($q) => $q->where('id', $user->barber_id))
        ->orderBy('name')->get()
        ->map(fn ($b) => ['name' => $b->name, 'goal' => (int) $b->weekly_goal, 'done' => (int) ($counts[$b->id] ?? 0)]);
}

// Recordatorios pendientes de mañana
$tomorrow = Appointment::where('status', 'pendiente')
    ->whereDate('starts_at', today()->addDay())
    ->when($user->isBarber(), fn ($q) => $q->where('barber_id', $user->barber_id))
    ->selectRaw('COUNT(*) as total, SUM(reminded_at IS NULL) as unsent')
    ->first();

$reminders = ['total' => (int) $tomorrow->total, 'unsent' => (int) $tomorrow->unsent];

         return view('agenda', [
        'date' => $date,
        'appointments' => $appointments,
        'columns' => $columns,
        'goals' => $goals,
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

    $barber = Barber::findOrFail($data['barber_id']);

    return redirect()
        ->route('agenda', [
            'fecha' => $appointment->starts_at->toDateString(),
        ])
        ->with(
            'ok',
            "Cita agendada: {$data['client_name']} {$data['client_last_name']} · "
            . $data['starts_at']->translatedFormat('D j M')
            . ' a las '
            . $data['starts_at']->format('g:i A')
            . " con {$barber->name}."
        );
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
        ->with(
            'ok',
            "Cita de {$appointment->client_name} {$appointment->client_last_name} actualizada: "
            . $f['starts_at']->translatedFormat('D j M')
            . ' a las '
            . $f['starts_at']->format('g:i A')
            . '.'
        );
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

    $name = "{$appointment->client_name} {$appointment->client_last_name}";

    $appointment->delete();

    return redirect()
        ->route('agenda', ['fecha' => $date])
        ->with('ok', "La cita de {$name} fue eliminada.");
}

    public function updateStatus(Request $request, Appointment $appointment, ProofStore $proofs)
{
    $this->own($appointment);

    $data = $request->validate([
        'status'         => 'required|in:pendiente,completada,cancelada,no_asistio',
        'payment_method' => 'required_if:status,completada|nullable|in:efectivo,sinpe,tarjeta',
        'tip'            => 'nullable|integer|min:0|max:1000000',
        'sinpe_ref'      => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\- ]+$/'],
        'proof'          => [
            Rule::requiredIf(fn () => config('barberia.require_sinpe_proof')
                && $request->status === 'completada' && $request->payment_method === 'sinpe'),
            'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144',
        ],
    ], [
        'payment_method.required_if' => 'Elige el método de pago.',
        'proof.required'  => 'Adjunta la foto del comprobante SINPE.',
        'proof.image'     => 'El comprobante debe ser una imagen.',
        'proof.mimes'     => 'El comprobante debe ser JPG, PNG o WEBP.',
        'proof.max'       => 'La foto pesa demasiado (máximo 6 MB).',
        'proof.uploaded'  => 'No se pudo subir la foto. Revisa tu conexión e inténtalo de nuevo.',
        'sinpe_ref.regex' => 'La referencia solo puede tener letras, números y guiones.',
    ]);

    abort_if(
        auth()->user()->isReception() && ($data['status'] === 'completada' || $appointment->status === 'completada'),
        403,
        'La recepción no puede cobrar ni reabrir citas.'
    );

    if ($data['status'] === 'completada') {
        $barberAmount = (int) round($appointment->price * $appointment->commission_percent / 100);
        $appointment->barber_amount  = $barberAmount;
        $appointment->owner_amount   = $appointment->price - $barberAmount;
        $appointment->payment_method = $data['payment_method'];
        $appointment->tip            = (int) ($data['tip'] ?? 0);
        $appointment->completed_at   = now();

        if ($data['payment_method'] === 'sinpe') {
            if ($request->hasFile('proof')) {
                $proofs->delete($appointment->proof_path);
                $appointment->proof_path = $proofs->store($request->file('proof'), $appointment);
            }
            $appointment->sinpe_ref = $data['sinpe_ref'] ?? null;
        } else {
            $this->dropProof($appointment, $proofs);   // si cambió de SINPE a otro método
        }
    } else {
        $appointment->barber_amount  = 0;
        $appointment->owner_amount   = 0;
        $appointment->payment_method = null;
        $appointment->tip            = 0;
        $appointment->completed_at   = null;
        $this->dropProof($appointment, $proofs);
    }

    $appointment->status = $data['status'];
    $appointment->save();

    $name   = "{$appointment->client_name} {$appointment->client_last_name}";
    $method = ['efectivo' => 'efectivo', 'sinpe' => 'SINPE', 'tarjeta' => 'tarjeta'][$appointment->payment_method] ?? '';
    $paid   = '₡' . number_format($appointment->price + $appointment->tip, 0, ',', '.');

    $msg = match ($data['status']) {
        'completada' => "Cobro registrado: {$name} pagó {$paid} por {$method}"
            . ($appointment->tip ? ' (incluye ₡' . number_format($appointment->tip, 0, ',', '.') . ' de propina)' : '') . '.',
        'cancelada'  => "La cita de {$name} fue cancelada.",
        'no_asistio' => "Marcada como «no vino»: {$name}.",
        default      => "La cita de {$name} volvió a quedar pendiente.",
    };

    $redirect = back()->with('ok', $msg);

    if ($data['status'] === 'completada' && $appointment->payment_method === 'sinpe' && ! $appointment->proof_path) {
        $redirect->with('warn', "Falta el comprobante SINPE de {$name}. Puedes adjuntarlo tocando la cita.");
    }

    return $redirect;
}

private function dropProof(Appointment $appointment, ProofStore $proofs): void
{
    $proofs->delete($appointment->proof_path);
    $appointment->proof_path = null;
    $appointment->sinpe_ref  = null;
}

    
    /** Horas de inicio libres según la duración del servicio: normales, más temprano y más tarde **/
public function availableSlots(Request $request)
{
    $data = $request->validate([
        'barber_id'  => 'required|exists:barbers,id',
        'service_id' => 'required|exists:services,id',
        'date'       => 'required|date',
        'exclude'    => 'nullable|integer',
    ]);

    $user = auth()->user();
    abort_if($user->isBarber() && $data['barber_id'] != $user->barber_id, 403);

    $dur = (int) Service::findOrFail($data['service_id'])->duration_minutes;
    $day = Carbon::parse($data['date']);

    $dayStart = $day->copy()->startOfDay();
    $dayEnd   = $day->copy()->endOfDay();

    // Citas del barbero ese día (en minutos desde medianoche)
    $busy = Appointment::where('barber_id', $data['barber_id'])
        ->whereIn('status', ['pendiente', 'completada'])
        ->whereDate('starts_at', $day)
        ->when($data['exclude'] ?? null, fn ($q, $id) => $q->where('id', '!=', $id))
        ->get(['starts_at', 'ends_at'])
        ->map(fn ($a) => [
            $a->starts_at->hour * 60 + $a->starts_at->minute,
            $a->ends_at->isSameDay($a->starts_at) ? $a->ends_at->hour * 60 + $a->ends_at->minute : 1440,
        ]);

    // Los bloqueos (del barbero o de toda la barbería) también ocupan tiempo
    $blocked = TimeBlock::overlapping($dayStart, $dayEnd)
        ->forBarber($data['barber_id'])
        ->get()
        ->map(fn ($b) => [
            (int) $dayStart->diffInMinutes($b->starts_at->max($dayStart)),
            (int) $dayStart->diffInMinutes($b->ends_at->min($dayEnd)),
        ]);

    $busy = $busy->concat($blocked)->sortBy(0)->values();

    [$winFrom, $winTo] = $this->dayBounds();
    $open  = (int) config('barberia.open_hour') * 60;
    $close = (int) config('barberia.close_hour') * 60;

    // Hoy: no se ofrecen horas que ya pasaron (redondeado al próximo cuarto de hora)
    $nowMin = 0;
    if ($day->isToday()) {
        $n      = now();
        $nowMin = (int) (ceil(($n->hour * 60 + $n->minute) / 15) * 15);
    }

    $fmt  = fn (int $t) => sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
    $free = fn (int $t) => $busy->every(fn ($b) => $b[1] <= $t || $b[0] >= $t + $dur);

    // 1) Horario normal: pegado a cada espacio libre, de apertura a cierre
    $slots  = [];
    $cursor = max($open, $nowMin);

    foreach ($busy as [$s, $e]) {
        if ($e <= $cursor) continue;
        if ($s >= $close)  break;

        for ($t = $cursor; $t + $dur <= min($s, $close); $t += $dur) {
            $slots[] = $fmt($t);
        }
        $cursor = max($cursor, $e);
    }
    for ($t = $cursor; $t + $dur <= $close; $t += $dur) {
        $slots[] = $fmt($t);
    }

    // 2) Fuera del horario normal: cada 30 minutos, si cabe y está libre
    $early = [];
    $late  = [];
    $first = (int) (ceil(max($winFrom, $nowMin) / 30) * 30);

    for ($t = $first; $t + $dur <= $winTo; $t += 30) {
        $outside = $t < $open || $t + $dur > $close;
        if (! $outside || ! $free($t)) continue;

        if ($t < $open) $early[] = $fmt($t);
        else            $late[]  = $fmt($t);
    }

    return response()->json(compact('slots', 'early', 'late'));
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

  // 1) Dentro del rango en que se puede agendar (puede ser fuera del horario normal)
[$winFrom, $winTo] = $this->dayBounds();

$startMin = $start->hour * 60 + $start->minute;
$endMin   = $startMin + $service->duration_minutes;

if ($startMin < $winFrom || $endMin > $winTo) {
    throw ValidationException::withMessages([
        'time' => 'Ese servicio no cabe en el horario permitido (' .
            $this->hm($winFrom) . ' a ' . $this->hm($winTo) . ').',
    ]);
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