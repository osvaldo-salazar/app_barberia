<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\ActivityLog;
use Illuminate\Auth\Events\{Failed, Lockout, Logout};
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Support\Facades\Event;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
        {
            Paginator::useBootstrapFive();
            ResetPassword::toMailUsing(function ($notifiable, string $token) {
    $url = url(route('password.reset', [
        'token' => $token,
        'email' => $notifiable->getEmailForPasswordReset(),
    ], false));

    return (new MailMessage)
        ->subject('Cambia tu clave de ' . config('app.name'))
        ->view('emails.password-link', [
            'name'    => $notifiable->name,
            'url'     => $url,
            'minutes' => config('auth.passwords.' . config('auth.defaults.passwords') . '.expire'),
        ]);
    });

    Event::listen(Failed::class, fn ($e) => ActivityLog::note(
    'Intento de acceso fallido con el correo ' . ($e->credentials['email'] ?? '—'), 'failed', null, $e->user
));
Event::listen(Lockout::class, fn ($e) => ActivityLog::note(
    'Bloqueo temporal por demasiados intentos con el correo ' . $e->request->input('email', '—'), 'failed'
));
Event::listen(Logout::class, fn ($e) => ActivityLog::note('Cerró sesión', 'logout', null, $e->user));
Event::listen(PasswordResetEvent::class, fn ($e) => ActivityLog::note(
    'Cambió su clave con el enlace del correo', 'security', null, $e->user
));
  }
}
