<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\CommissionController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Auth::routes(['register' => false]);

Route::middleware('auth')->group(function () {
    Route::get('/', [AppointmentController::class, 'index'])->name('agenda');
    Route::post('/citas', [AppointmentController::class, 'store'])->name('citas.store');
    Route::patch('/citas/{appointment}/estado', [AppointmentController::class, 'updateStatus'])->name('citas.estado');
    Route::get('/comisiones', [CommissionController::class, 'index'])->name('comisiones');
}); 
Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
