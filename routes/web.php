<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BarberController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Auth::routes(['register' => false]);

Route::middleware('auth')->group(function () {

    // Agenda y citas: admin, barbero y recepcion
    Route::middleware('role:admin,barbero,recepcion')->group(function () {
        Route::get('/', [AppointmentController::class, 'index'])->name('agenda');
        Route::post('/citas', [AppointmentController::class, 'store'])->name('citas.store');
        Route::get('/citas/{appointment}/editar', [AppointmentController::class, 'edit'])->name('citas.edit');
        Route::put('/citas/{appointment}', [AppointmentController::class, 'update'])->name('citas.update');
        Route::delete('/citas/{appointment}', [AppointmentController::class, 'destroy'])->name('citas.destroy');
        Route::patch('/citas/{appointment}/estado', [AppointmentController::class, 'updateStatus'])->name('citas.estado');
    });

    // Comisiones: admin y barbero
    Route::middleware('role:admin,barbero')->group(function () {
        Route::get('/comisiones', [CommissionController::class, 'index'])->name('comisiones');
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
    });
});
