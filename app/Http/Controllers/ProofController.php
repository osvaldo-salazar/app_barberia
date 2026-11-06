<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\ProofStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProofController extends Controller
{
    public function __construct(private ProofStore $proofs) {}

    public function show(Appointment $appointment)
{
    $this->allow($appointment);

    $disk = Storage::disk('local');
    abort_unless($appointment->proof_path && $disk->exists($appointment->proof_path), 404);

    return response()->file($disk->path($appointment->proof_path), [
        'Content-Type'           => 'image/jpeg',
        'Cache-Control'          => 'private, max-age=3600',
        'X-Content-Type-Options' => 'nosniff',
    ]);
}

    public function store(Request $request, Appointment $appointment)
    {
        $this->allow($appointment);
        abort_unless(
            $appointment->status === 'completada' && $appointment->payment_method === 'sinpe',
            422, 'Solo los cobros por SINPE llevan comprobante.'
        );

        $data = $request->validate([
            'proof'     => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'sinpe_ref' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\- ]+$/'],
        ], [
            'proof.required'  => 'Elige la foto del comprobante.',
            'proof.image'     => 'El comprobante debe ser una imagen.',
            'proof.mimes'     => 'El comprobante debe ser JPG, PNG o WEBP.',
            'proof.max'       => 'La foto pesa demasiado (máximo 6 MB).',
            'proof.uploaded'  => 'No se pudo subir la foto. Revisa tu conexión e inténtalo de nuevo.',
            'sinpe_ref.regex' => 'La referencia solo puede tener letras, números y guiones.',
        ]);

        $old  = $appointment->proof_path;
        $path = $this->proofs->store($request->file('proof'), $appointment);

        $appointment->update([
            'proof_path' => $path,
            'sinpe_ref'  => $data['sinpe_ref'] ?? $appointment->sinpe_ref,
        ]);
        $this->proofs->delete($old);

        return back()->with('ok', "Comprobante guardado para {$appointment->client_name} {$appointment->client_last_name}.");
    }

    public function destroy(Appointment $appointment)
    {
        $this->allow($appointment);

        $this->proofs->delete($appointment->proof_path);
        $appointment->update(['proof_path' => null, 'sinpe_ref' => null]);

        return back()->with('ok', 'Comprobante eliminado.');
    }

    /** Admin: todos. Barbero: solo sus citas. Recepción: nada. */
    private function allow(Appointment $appointment): void
    {
        $u = auth()->user();
        abort_if($u->isReception(), 403);
        abort_if($u->isBarber() && $appointment->barber_id != $u->barber_id, 403);
    }
}