<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
    {
        const OPEN_HOUR = 10;
        const CLOSE_HOUR = 19;

        public function index(Request $request)
    {
        $request->validate(['fecha' => 'nullable|date']);

        $user = auth()->user();
        abort_if($user->isBarber() && ! $user->barber_id, 403, 'Tu usuario no está vinculado a un barbero.');

        $date = $request->filled('fecha') ? Carbon::parse($request->fecha) : today();

        $appointments = Appointment::with(['barber', 'service'])
            ->whereDate('starts_at', $date)
            ->when($user->isBarber(), fn ($q) => $q->where('barber_id', $user->barber_id))
            ->orderBy('starts_at')
            ->get();

        // Columnas: barberos activos + los que tengan citas ese día aunque ya estén inactivos o eliminados
        $columns = Barber::withTrashed()
            ->where(function ($q) use ($appointments) {
                $q->where(fn ($w) => $w->whereNull('deleted_at')->where('active', true))
                ->orWhereIn('id', $appointments->pluck('barber_id'));
            })
            ->when($user->isBarber(), fn ($q) => $q->where('id', $user->barber_id))
            ->orderBy('name')
            ->get();

        $done = $appointments->where('status', 'completada');

        $stats = [
            'total'   => $appointments->where('status', '!=', 'cancelada')->count(),
            'pending' => $appointments->where('status', 'pendiente')->count(),
            'done'    => $done->count(),
            'money'   => $user->isBarber()
                            ? $done->sum('barber_amount') + $done->sum('tip')
                            : $done->sum('price'),
        ];

        return view('agenda', [
            'date'         => $date,
            'appointments' => $appointments,
            'columns'      => $columns,
            'barbers'      => Barber::where('active', true)
                                ->when($user->isBarber(), fn ($q) => $q->where('id', $user->barber_id))
                                ->orderBy('name')->get(),
            'services'     => Service::where('active', true)->orderBy('name')->get(),
            'slots'        => $this->slots(),
            'stats'        => $stats,
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

        $appointment->load(['barber', 'service']);

        $barbers = Barber::where('active', true)
            ->orderBy('name')
            ->get();

        $services = Service::where('active', true)
            ->orderBy('name')
            ->get();

        $slots = $this->slots();

        return view('citas.edit', compact(
            'appointment',
            'barbers',
            'services',
            'slots'
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
     * Genera los horarios disponibles cada 30 minutos.
     */
    private function slots()
    {
        return collect(range(0, 17))->map(
            fn ($i) => Carbon::createFromTime(self::OPEN_HOUR, 0)
                ->addMinutes(30 * $i)
                ->format('H:i')
        );
    }

    /**
     * Valida y arma los datos de la cita.
     * Sirve para crear y editar.
     */
    private function resolve(
        Request $request,
        ?Appointment $current = null
    ): array {
        $user = auth()->user();

        // El barbero solo puede asignarse a sí mismo.
        if ($user->isBarber()) {
            abort_if(
                ! $user->barber_id,
                403,
                'Tu usuario no está vinculado a un barbero.'
            );

            $request->merge([
                'barber_id' => $user->barber_id,
            ]);
        }

        $phone = preg_replace('/\D/', '', (string) $request->phone);

        if (strlen($phone) === 11 && str_starts_with($phone, '506')) {
            $phone = substr($phone, 3);
        }

        $request->merge(['phone' => $phone]);

        $data = $request->validate([
            'barber_id' => 'required|exists:barbers,id',
            'service_id' => 'required|exists:services,id',
            'client_name' => 'required|string|max:60',
            'client_last_name' => 'required|string|max:60',
            'phone' => ['required', 'regex:/^[2-8]\d{7}$/'],
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
        ], [
            'phone.regex' => 'El teléfono debe tener 8 dígitos (Costa Rica).',
        ]);

        $service = Service::findOrFail($data['service_id']);
        $barber = Barber::findOrFail($data['barber_id']);

        $start = Carbon::parse($data['date'] . ' ' . $data['time']);
        $end = $start->copy()->addMinutes($service->duration_minutes);

        $open = $start->copy()->setTime(self::OPEN_HOUR, 0);
        $close = $start->copy()->setTime(self::CLOSE_HOUR, 0);

        if ($start < $open || $end > $close) {
            throw ValidationException::withMessages([
                'time' => 'Ese servicio no cabe dentro del horario (10am - 7pm).',
            ]);
        }

        $overlap = Appointment::where('barber_id', $barber->id)
            ->whereIn('status', ['pendiente', 'completada'])
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when(
                $current,
                fn ($q) => $q->where('id', '!=', $current->id)
            )
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'time' => 'Ese barbero ya tiene una cita a esa hora.',
            ]);
        }

        // Al editar, conserva precio y porcentaje si no cambian
        // el servicio o el barbero.
        $price = (
            $current && $current->service_id == $service->id
        )
            ? $current->price
            : $service->price;

        $percent = (
            $current && $current->barber_id == $barber->id
        )
            ? $current->commission_percent
            : $barber->commission_percent;

        return [
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'client_name' => $data['client_name'],
            'client_last_name' => $data['client_last_name'],
            'phone' => $data['phone'],
            'starts_at' => $start,
            'ends_at' => $end,
            'price' => $price,
            'commission_percent' => $percent,
        ];
    }
}
