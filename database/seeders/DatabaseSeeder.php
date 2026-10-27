<?php

namespace Database\Seeders;

use App\Models\{Barber, Service, User};
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
{
    User::create([
        'name' => 'Admin',
        'email' => 'tucorreo@ejemplo.com',
        'password' => Hash::make('cambia_esta_clave'),
    ]);

    // Ajusta nombres y porcentajes a tu realidad
    Barber::create(['name' => 'Tu nombre', 'commission_percent' => 50]);
    Barber::create(['name' => 'Barbero 2', 'commission_percent' => 50]);

    // Ajusta los precios
    Service::create(['name' => 'Corte', 'price' => 5000, 'duration_minutes' => 30]);
    Service::create(['name' => 'Corte + barba', 'price' => 7000, 'duration_minutes' => 60]);
    Service::create(['name' => 'Barba', 'price' => 3000, 'duration_minutes' => 30]);
}
}
