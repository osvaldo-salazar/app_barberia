<?php

namespace Database\Seeders;

use App\Models\{Barber, Service, User};
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminName = trim((string) config('admin.name'));
        $adminEmail = trim((string) config('admin.email'));
        $adminPassword = (string) config('admin.password');

        if ($adminName === '' || $adminEmail === '' || $adminPassword === '') {
            throw new RuntimeException(
                'Configura ADMIN_NAME, ADMIN_EMAIL y ADMIN_PASSWORD en el archivo .env antes de ejecutar los seeders.'
            );
        }

        if (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                'ADMIN_EMAIL no contiene una direccion de correo valida.'
            );
        }

        User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
            ]
        );

        // Ajusta nombres y porcentajes a tu realidad.
        Barber::create([
            'name' => 'Tu nombre',
            'commission_percent' => 50,
        ]);

        Barber::create([
            'name' => 'Barbero 2',
            'commission_percent' => 50,
        ]);

        // Ajusta los precios a tu realidad.
        Service::create([
            'name' => 'Corte',
            'price' => 5000,
            'duration_minutes' => 30,
        ]);

        Service::create([
            'name' => 'Corte + barba',
            'price' => 7000,
            'duration_minutes' => 60,
        ]);

        Service::create([
            'name' => 'Barba',
            'price' => 3000,
            'duration_minutes' => 30,
        ]);
    }
}