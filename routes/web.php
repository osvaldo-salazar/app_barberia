<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BarberController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Auth::routes(['register' => false]);

Route::middleware('auth')->group(function () {
    // Agenda
    Route::get('/', [AppointmentController::class, 'index'])->name('agenda');
    Route::post('/citas', [AppointmentController::class, 'store'])->name('citas.store');
    Route::patch('/citas/{appointment}/estado', [AppointmentController::class, 'updateStatus'])->name('citas.estado');

    // Comisiones
    Route::get('/comisiones', [CommissionController::class, 'index'])->name('comisiones');

    // Barberos
    Route::resource('barberos', BarberController::class)
        ->parameters(['barberos' => 'barber'])
        ->except(['show', 'destroy']);
    Route::patch('/barberos/{barber}/activo', [BarberController::class, 'toggle'])->name('barberos.toggle');

    // Servicios
    Route::resource('servicios', ServiceController::class)
        ->parameters(['servicios' => 'service'])
        ->except(['show', 'destroy']);
    Route::patch('/servicios/{service}/activo', [ServiceController::class, 'toggle'])->name('servicios.toggle');
});