
<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BarberController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BlockController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\WeekController;
use App\Http\Controllers\ProofController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ActivityController;

Auth::routes(['register' => false, 'reset' => true]);
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
        Route::get('/citas/{appointment}/comprobante', [ProofController::class, 'show'])->name('citas.comprobante.show');
        Route::post('/citas/{appointment}/comprobante', [ProofController::class, 'store'])->name('citas.comprobante.store');
        Route::delete('/citas/{appointment}/comprobante', [ProofController::class, 'destroy'])->name('citas.comprobante.destroy');
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

        // Actividad
        Route::get('/actividad', [ActivityController::class, 'index'])->name('actividad');

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

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

    });

});
