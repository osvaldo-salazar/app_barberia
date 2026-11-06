<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Log, Mail, Password};
use Illuminate\Support\Str;
use App\Models\ActivityLog;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordLinkController extends Controller
{
    public function request()
    {
        return view('auth.passwords.email');
    }

    public function email(Request $request)
    {
            $data = $request->validate(['email' => ['required', 'email', 'max:120']], [
            'email.required' => 'Escribe tu correo.',
            'email.email'    => 'Escribe un correo válido.',
        ]);

        ActivityLog::note(
            'Pidió un enlace de recuperación de clave para ' . $data['email'],
            'security'
        );

        try {
            Password::sendResetLink($this->eligible($data['email']));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el enlace de recuperación: ' . $e->getMessage());
        }

        // Siempre la misma respuesta: no revelamos si el correo existe
        return back()->with('status',
            'Si el correo corresponde a un barbero o administrador, te enviamos un enlace para cambiar tu clave. '
            . 'Vence en ' . config('auth.passwords.users.expire') . ' minutos. Revisa también la carpeta de spam. '
            . 'Si pediste uno hace menos de un minuto, espera antes de pedir otro.'
        );
    }

    public function form(Request $request, string $token)
    {
        return view('auth.passwords.reset', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ], [
            'password.required'  => 'Escribe tu clave nueva.',
            'password.confirmed' => 'Las dos claves no coinciden.',
        ]);

        $status = Password::reset(
            $this->eligible($data['email']) + [
                'password'              => $data['password'],
                'password_confirmation' => $request->password_confirmation,
                'token'                 => $data['token'],
            ],
            function ($user, $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),     // cierra los "recordarme" abiertos
                ])->save();

                event(new PasswordReset($user));

                try {
                    Mail::raw(
                        "Hola {$user->name}, la clave de tu cuenta en " . config('app.name') . ' se cambió el '
                        . now()->translatedFormat('j M Y, g:i A') . '. Si no fuiste tú, avisa al administrador de inmediato.',
                        fn ($m) => $m->to($user->email)->subject('Tu clave fue cambiada')
                    );
                } catch (\Throwable $e) {
                    Log::warning('No se pudo enviar el aviso de cambio de clave: ' . $e->getMessage());
                }
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', '¡Listo! Tu clave se cambió. Ya puedes iniciar sesión.');
        }

        // Enlace vencido, ya usado o de una cuenta no habilitada: mismo mensaje para todos
        return back()->withInput($request->only('email'))
            ->withErrors(['email' => 'Este enlace no es válido o ya venció. Pide uno nuevo.']);
    }

    /** Solo admin y barberos activos pueden recuperar la clave por correo */
    private function eligible(string $email): array
    {
        return ['email' => Str::lower(trim($email)), 'active' => 1, 'role' => ['admin', 'barbero']];
    }
}