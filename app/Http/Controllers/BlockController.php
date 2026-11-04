<?php

namespace App\Http\Controllers;

use App\Models\{Appointment, Barber, TimeBlock};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BlockController extends Controller
{
    const MAX_DAYS = 62;

    public function index()
    {
        $user = auth()->user();
        abort_if($user->isBarber() && ! $user->barber_id, 403, 'Tu usuario no está vinculado a un barbero.');

        $blocks = TimeBlock::with('barber')
            ->where('ends_at', '>=', now()->startOfDay())
            ->when($user->isBarber(), fn ($q) => $q->where('barber_id', $user->barber_id))
            ->orderBy('starts_at')
            ->limit(200)
            ->get();

        $barbers = Barber::where('active', true)
            ->when($user->isBarber(), fn ($q) => $q->where('id', $user->barber_id))
            ->orderBy('name')
            ->get();

        return view('bloqueos.index', compact('blocks', 'barbers'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if ($user->isBarber()) {
            abort_unless($user->barber_id, 403);
            $request->merge(['barber_id' => $user->barber_id]);
        }

        $data = $request->validate([
            'barber_id'  => ['nullable', Rule::exists('barbers', 'id')->where(fn ($q) => $q->whereNull('deleted_at')->where('active', 1))],
            'mode'       => ['required', 'in:day,hours'],
            'from'       => ['required', 'date_format:Y-m-d'],
            'to'         => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'start_time' => ['required_if:mode,hours', 'nullable', 'date_format:H:i', 'after_or_equal:10:00'],
            'end_time'   => ['required_if:mode,hours', 'nullable', 'date_format:H:i', 'after:start_time', 'before_or_equal:19:00'],
            'reason'     => ['nullable', 'string', 'max:80'],
        ], [
            'barber_id.exists'           => 'Ese barbero ya no está disponible.',
            'mode.required'              => 'Elige si es día completo o por horas.',
            'from.required'              => 'Elige la fecha inicial.',
            'to.after_or_equal'          => 'La fecha final no puede ser antes de la inicial.',
            'start_time.required_if'     => 'Elige la hora inicial.',
            'start_time.after_or_equal'  => 'La hora inicial no puede ser antes de las 10:00 AM.',
            'end_time.required_if'       => 'Elige la hora final.',
            'end_time.after'             => 'La hora final debe ser después de la inicial.',
            'end_time.before_or_equal'   => 'La hora final no puede pasar de las 7:00 PM.',
        ]);

        $from = Carbon::createFromFormat('Y-m-d', $data['from'])->startOfDay();
        $to   = ! empty($data['to']) ? Carbon::createFromFormat('Y-m-d', $data['to'])->startOfDay() : $from->copy();

        $rows = [];

        if ($data['mode'] === 'day') {
            // Un solo bloqueo que cubre todos los días del rango
            $rows[] = [$from->copy(), $to->copy()->endOfDay(), true];
        } else {
            if ($from->diffInDays($to) >= self::MAX_DAYS) {
                throw ValidationException::withMessages(['to' => 'Para bloqueos por horas el rango máximo es de ' . self::MAX_DAYS . ' días.']);
            }
            // Un bloqueo por cada día, a la misma hora
            for ($d = $from->copy(); $d <= $to; $d->addDay()) {
                $rows[] = [
                    $d->copy()->setTimeFromTimeString($data['start_time']),
                    $d->copy()->setTimeFromTimeString($data['end_time']),
                    false,
                ];
            }
        }

        $conflicts = 0;

        foreach ($rows as [$s, $e, $allDay]) {
            TimeBlock::create([
                'barber_id' => $data['barber_id'] ?? null,
                'starts_at' => $s,
                'ends_at'   => $e,
                'all_day'   => $allDay,
                'reason'    => $data['reason'] ?? null,
                'user_id'   => $user->id,
            ]);

            $conflicts += Appointment::where('status', 'pendiente')
                ->when($data['barber_id'] ?? null, fn ($q, $id) => $q->where('barber_id', $id))
                ->where('starts_at', '<', $e)
                ->where('ends_at', '>', $s)
                ->count();
        }

        $redirect = redirect()->route('bloqueos.index')->with('ok', 'Bloqueo creado.');

        if ($conflicts) {
            $redirect->with('warn', "Ojo: hay {$conflicts} " . ($conflicts === 1 ? 'cita pendiente' : 'citas pendientes') .
                ' en ese horario. No se cancelaron; revísalas en la agenda.');
        }

        return $redirect;
    }

    public function destroy(TimeBlock $block)
    {
        $user = auth()->user();
        abort_if($user->isBarber() && (int) $block->barber_id !== (int) $user->barber_id, 403);

        $block->delete();

        return back()->with('ok', 'Bloqueo eliminado.');
    }
}