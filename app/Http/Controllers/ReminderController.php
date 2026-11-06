<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['fecha' => 'nullable|date_format:Y-m-d']);

        $user = auth()->user();
        abort_if($user->isBarber() && ! $user->barber_id, 403, 'Tu usuario no está vinculado a un barbero.');

        $date = $request->filled('fecha')
            ? Carbon::createFromFormat('Y-m-d', $request->fecha)->startOfDay()
            : today()->addDay();

        $items = Appointment::with(['barber', 'service'])
            ->where('status', 'pendiente')
            ->whereDate('starts_at', $date)
            ->when($user->isBarber(), fn ($q) => $q->where('barber_id', $user->barber_id))
            ->orderBy('starts_at')
            ->get()
            ->map(function ($a) {
                $text = strtr(config('barberia.reminder_template'), [
                    ':nombre'   => $a->client_name,
                    ':barberia' => config('app.name'),
                    ':dia'      => $a->starts_at->translatedFormat('l j \d\e F'),
                    ':hora'     => $a->starts_at->format('g:i A'),
                    ':barbero'  => $a->barber->name,
                ]);
                $a->wa_url = 'https://wa.me/506' . $a->phone . '?text=' . rawurlencode($text);

                return $a;
            });

        return view('recordatorios.index', ['date' => $date, 'items' => $items]);
    }

    public function mark(Request $request, Appointment $appointment)
    {
        $user = auth()->user();
        abort_if($user->isBarber() && $appointment->barber_id != $user->barber_id, 403);

        $appointment->update(['reminded_at' => $request->boolean('undo') ? null : now()]);

        return response()->json([
            'sent' => (bool) $appointment->reminded_at,
            'at'   => $appointment->reminded_at?->format('g:i A'),
        ]);
    }
}