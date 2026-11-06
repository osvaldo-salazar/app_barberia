<?php

use App\Http\Controllers\{
    ActivityController, AppointmentController, BarberController, BlockController, CashController,
    ClientController, CommissionController, DashboardController, ExpenseController, ProofController,
    ReminderController, ServiceController, UserController, WeekController};
use App\Http\Controllers\Auth\PasswordLinkController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Auth::routes(['register' => false, 'reset' => false]);

// Recuperar la clave con enlace al correo
Route::middleware('guest')->prefix('clave')->group(function () {
    Route::get('/olvide', [PasswordLinkController::class, 'request'])->name('password.request');
    Route::post('/olvide', [PasswordLinkController::class, 'email'])->middleware('throttle:6,10')->name('password.email');
    Route::get('/restablecer/{token}', [PasswordLinkController::class, 'form'])->name('password.reset');
    Route::post('/restablecer', [PasswordLinkController::class, 'update'])->middleware('throttle:6,10')->name('password.update');
});

Route::middleware('auth')->group(function () {

    // ===== Admin, barberos y recepción =====
    Route::middleware('role:admin,barbero,recepcion')->group(function () {
        // Agenda y citas
        Route::get('/', [AppointmentController::class, 'index'])->name('agenda');
        Route::post('/citas', [AppointmentController::class, 'store'])->name('citas.store');
        Route::get('/citas/horarios', [AppointmentController::class, 'availableSlots'])->name('citas.horarios');
        Route::get('/citas/calendario', [AppointmentController::class, 'calendar'])->name('citas.calendario');
        Route::get('/citas/{appointment}/editar', [AppointmentController::class, 'edit'])->name('citas.edit');
        Route::put('/citas/{appointment}', [AppointmentController::class, 'update'])->name('citas.update');
        Route::delete('/citas/{appointment}', [AppointmentController::class, 'destroy'])->name('citas.destroy');
        Route::patch('/citas/{appointment}/estado', [AppointmentController::class, 'updateStatus'])->name('citas.estado');

        // Clientes
        Route::get('/clientes/buscar', [ClientController::class, 'search'])->name('clientes.search');
        Route::get('/clientes', [ClientController::class, 'index'])->name('clientes.index');
        Route::get('/clientes/{client}', [ClientController::class, 'show'])->name('clientes.show');
        Route::put('/clientes/{client}', [ClientController::class, 'update'])->name('clientes.update');

        // Recordatorios
        Route::get('/recordatorios', [ReminderController::class, 'index'])->name('recordatorios.index');
        Route::post('/recordatorios/{appointment}', [ReminderController::class, 'mark'])->name('recordatorios.mark');
       
    });

    // ===== Admin y barberos =====
    Route::middleware('role:admin,barbero')->group(function () {
        Route::get('/comisiones', [CommissionController::class, 'index'])->name('comisiones');

        Route::get('/bloqueos', [BlockController::class, 'index'])->name('bloqueos.index');
        Route::post('/bloqueos', [BlockController::class, 'store'])->name('bloqueos.store');
        Route::delete('/bloqueos/{block}', [BlockController::class, 'destroy'])->name('bloqueos.destroy');

        Route::get('/citas/{appointment}/comprobante', [ProofController::class, 'show'])->name('citas.comprobante.show');
        Route::post('/citas/{appointment}/comprobante', [ProofController::class, 'store'])->name('citas.comprobante.store');
        Route::delete('/citas/{appointment}/comprobante', [ProofController::class, 'destroy'])->name('citas.comprobante.destroy');
    });

    // ===== Solo admin =====
    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/actividad', [ActivityController::class, 'index'])->name('actividad');

        // Caja diaria
        Route::get('/caja', [CashController::class, 'show'])->name('caja');
        Route::post('/caja', [CashController::class, 'store'])->name('caja.store');
        Route::get('/caja/pdf', [CashController::class, 'pdf'])->name('caja.pdf');
        Route::post('/caja/movimientos', [CashController::class, 'storeEntry'])->name('caja.mov.store');
        Route::delete('/caja/movimientos/{entry}', [CashController::class, 'destroyEntry'])->name('caja.mov.destroy');

        // Cierre semanal
        Route::get('/semana', [WeekController::class, 'show'])->name('semana');
        Route::get('/semana/pdf', [WeekController::class, 'pdf'])->name('semana.pdf');
        Route::get('/semana/excel', [WeekController::class, 'csv'])->name('semana.csv');
        Route::post('/semana/pagos', [WeekController::class, 'pay'])->name('semana.pagar');
        Route::delete('/semana/pagos/{payout}', [WeekController::class, 'unpay'])->name('semana.deshacer');

        // Gastos
        Route::get('/gastos', [ExpenseController::class, 'index'])->name('gastos.index');
        Route::post('/gastos', [ExpenseController::class, 'store'])->name('gastos.store');
        Route::delete('/gastos/{expense}', [ExpenseController::class, 'destroy'])->name('gastos.destroy');

        // Eliminar clientes
        Route::delete('/clientes/{client}', [ClientController::class, 'destroy'])->name('clientes.destroy');

        // Barberos, servicios y usuarios
        Route::resource('barberos', BarberController::class)->parameters(['barberos' => 'barber'])->except(['show']);
        Route::patch('/barberos/{barber}/activo', [BarberController::class, 'toggle'])->name('barberos.toggle');

        Route::resource('servicios', ServiceController::class)->parameters(['servicios' => 'service'])->except(['show']);
        Route::patch('/servicios/{service}/activo', [ServiceController::class, 'toggle'])->name('servicios.toggle');

        Route::resource('usuarios', UserController::class)->parameters(['usuarios' => 'user'])->except(['show']);
        Route::patch('/usuarios/{user}/activo', [UserController::class, 'toggle'])->name('usuarios.toggle');
    });
});