<?php

namespace App\Http\Controllers;

use App\Models\{Appointment, Barber, Service};
use Carbon\Carbon;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    const OPEN_HOUR  = 10; // 10:00 am
    const CLOSE_HOUR = 19; // 7:00 pm

    public function index(Request $request)
    {
        $date = $request->filled('fecha') ? Carbon::parse($request->fecha) : today();

        $appointments = Appointment::with(['barber', 'service'])
            ->whereDate('starts_at', $date)
            ->orderBy('starts_at')
            ->get();

        // Horas disponibles cada 30 min: 10:00 ... 18:30
        $slots = collect(range(0, 17))->map(
            fn ($i) => Carbon::createFromTime(self::OPEN_HOUR, 0)->addMinutes(30 * $i)->format('H:i')
        );

        return view('agenda', [
            'date'         => $date,
            'appointments' => $appointments,
            'barbers'      => Barber::where('active', true)->get(),
            'services'     => Service::where('active', true)->get(),
            'slots'        => $slots,
        ]);
    }

    public function store(Request $request)
    {
        // Limpia el teléfono: quita espacios, guiones y el +506
        $phone = preg_replace('/\D/', '', $request->phone);
        if (strlen($phone) === 11 && str_starts_with($phone, '506')) {
            $phone = substr($phone, 3);
        }
        $request->merge(['phone' => $phone]);

        $data = $request->validate([
            'barber_id'        => 'required|exists:barbers,id',
            'service_id'       => 'required|exists:services,id',
            'client_name'      => 'required|string|max:60',
            'client_last_name' => 'required|string|max:60',
            'phone'            => ['required', 'regex:/^[2-8]\d{7}$/'],
            'date'             => 'required|date',
            'time'             => 'required|date_format:H:i',
        ], [
            'phone.regex' => 'El teléfono debe tener 8 dígitos (Costa Rica).',
        ]);

        $service = Service::findOrFail($data['service_id']);
        $barber  = Barber::findOrFail($data['barber_id']);

        $start = Carbon::parse($data['date'] . ' ' . $data['time']);
        $end   = $start->copy()->addMinutes($service->duration_minutes);

        // 1) Dentro del horario (10am - 7pm, todos los días)
        $open  = $start->copy()->setTime(self::OPEN_HOUR, 0);
        $close = $start->copy()->setTime(self::CLOSE_HOUR, 0);
        if ($start < $open || $end > $close) {
            return back()->withErrors(['time' => 'Ese servicio no cabe dentro del horario (10am - 7pm).'])->withInput();
        }

        // 2) Que no choque con otra cita del mismo barbero
        $overlap = Appointment::where('barber_id', $barber->id)
            ->whereIn('status', ['pendiente', 'completada'])
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->exists();

        if ($overlap) {
            return back()->withErrors(['time' => 'Ese barbero ya tiene una cita a esa hora.'])->withInput();
        }

        Appointment::create([
            'barber_id'          => $barber->id,
            'service_id'         => $service->id,
            'client_name'        => $data['client_name'],
            'client_last_name'   => $data['client_last_name'],
            'phone'              => $data['phone'],
            'starts_at'          => $start,
            'ends_at'            => $end,
            'price'              => $service->price,
            'commission_percent' => $barber->commission_percent,
        ]);

        return redirect()->route('agenda', ['fecha' => $start->toDateString()])
            ->with('ok', 'Cita agendada.');
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        $request->validate(['status' => 'required|in:pendiente,completada,cancelada,no_asistio']);

        if ($request->status === 'completada') {
            $barberAmount = (int) round($appointment->price * $appointment->commission_percent / 100);
            $appointment->barber_amount = $barberAmount;
            $appointment->owner_amount  = $appointment->price - $barberAmount;
            $appointment->completed_at  = now();
        } else {
            $appointment->barber_amount = 0;
            $appointment->owner_amount  = 0;
            $appointment->completed_at  = null;
        }

        $appointment->status = $request->status;
        $appointment->save();

        return back()->with('ok', 'Estado actualizado.');
    }
}