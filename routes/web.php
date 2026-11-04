<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BarberController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BlockController;
use App\Http\Controllers\CashController;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Auth::routes(['register' => false, 'reset' => false]);

Route::middleware('auth')->group(function () {

    // Agenda, citas y clientes: admin, barbero y recepción
    Route::middleware('role:admin,barbero,recepcion')->group(function () {

        // Agenda
        Route::get('/', [AppointmentController::class, 'index'])->name('agenda');
        Route::post('/citas', [AppointmentController::class, 'store'])->name('citas.store');
        Route::get('/citas/{appointment}/editar', [AppointmentController::class, 'edit'])->name('citas.edit');
        Route::put('/citas/{appointment}', [AppointmentController::class, 'update'])->name('citas.update');
        Route::delete('/citas/{appointment}', [AppointmentController::class, 'destroy'])->name('citas.destroy');
        Route::patch('/citas/{appointment}/estado', [AppointmentController::class, 'updateStatus'])->name('citas.estado');
        Route::get('/citas/horarios', [AppointmentController::class, 'availableSlots'])->name('citas.horarios');
        Route::get('/citas/calendario', [AppointmentController::class, 'calendar'])->name('citas.calendario');

        // Clientes
        Route::get('/clientes/buscar', [ClientController::class, 'search'])->name('clientes.search');
        Route::get('/clientes', [ClientController::class, 'index'])->name('clientes.index');
        Route::get('/clientes/{client}', [ClientController::class, 'show'])->name('clientes.show');
        Route::put('/clientes/{client}', [ClientController::class, 'update'])->name('clientes.update');
    });

    // Comisiones y bloqueos: admin y barbero
    Route::middleware('role:admin,barbero')->group(function () {

        // Comisiones
        Route::get('/comisiones', [CommissionController::class, 'index'])->name('comisiones');

        // Bloqueos
        Route::get('/bloqueos', [BlockController::class, 'index'])->name('bloqueos.index');
        Route::post('/bloqueos', [BlockController::class, 'store'])->name('bloqueos.store');
        Route::delete('/bloqueos/{block}', [BlockController::class, 'destroy'])->name('bloqueos.destroy');
    });

    // Administración: solo admin
    Route::middleware('role:admin')->group(function () {

        // Barberos
        Route::resource('barberos', BarberController::class)
            ->parameters(['barberos' => 'barber'])
            ->except(['show']);

        Route::patch('/barberos/{barber}/activo', [BarberController::class, 'toggle'])
            ->name('barberos.toggle');

        // Servicios
        Route::resource('servicios', ServiceController::class)
            ->parameters(['servicios' => 'service'])
            ->except(['show']);

        Route::patch('/servicios/{service}/activo', [ServiceController::class, 'toggle'])
            ->name('servicios.toggle');

        // Usuarios
        Route::resource('usuarios', UserController::class)
            ->parameters(['usuarios' => 'user'])
            ->except(['show']);

        Route::patch('/usuarios/{user}/activo', [UserController::class, 'toggle'])
            ->name('usuarios.toggle');

        // Clientes: eliminación solo admin
        Route::delete('/clientes/{client}', [ClientController::class, 'destroy'])
            ->name('clientes.destroy');

        // Caja
        Route::get('/caja', [CashController::class, 'show'])->name('caja');
        Route::post('/caja', [CashController::class, 'store'])->name('caja.store');
    });
}); 