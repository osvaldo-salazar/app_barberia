<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\CashClosing;
use App\Models\CashEntry;
use App\Services\CashBook;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Service;
use App\Models\User;
use App\Models\WeekPayout;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // PROTECCION: solo se permite ejecutar en el entorno demo.
        if (app()->environment() !== 'demo') {
            throw new RuntimeException(
                'DETENIDO: el entorno no es demo.'
            );
        }

        if (DB::connection()->getDatabaseName() !== 'barberia_demo') {
            throw new RuntimeException(
                'DETENIDO: la base de datos no es barberia_demo.'
            );
        }

        // Comprobar que todas las tablas necesarias existen y están vacías.
        $tables = [
            'users',
            'barbers',
            'services',
            'clients',
            'appointments',
            'time_blocks',
            'cash_closings',
            'expenses',
            'cash_entries',
            'week_payouts',
            'activity_logs',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException(
                    "DETENIDO: no existe la tabla {$table}."
                );
            }

            if (DB::table($table)->exists()) {
                throw new RuntimeException(
                    "DETENIDO: la tabla {$table} ya contiene datos. No se modificó nada."
                );
            }
        }

        DB::transaction(function () {
            $this->createUsersAndBarbers();
            $services = $this->createServices();
            $clients = $this->createClients();

            $start = today()->subWeeks(5)->startOfWeek(Carbon::SUNDAY);
            $end = today();

            $this->createAppointments($start, $end, $services, $clients);
            $this->createExpenses($start, $end);
            $this->createCashEntries($start, $end);
            $this->createWeeklyPayouts($start, $end);
            $this->createCashClosings($start, $end);
        });

        $this->command?->info('Datos de demostración creados correctamente.');
        $this->command?->info('Base de datos: barberia_demo');
        $this->command?->info('Usuarios: admin@demo.test, barbero@demo.test, recepcion@demo.test');
        $this->command?->info('Contraseña de las cuentas: Demo12345');
    }

    private function createUsersAndBarbers(): void
    {
        $admin = User::create([
            'name' => 'Administrador Demo',
            'email' => 'admin@demo.test',
            'password' => Hash::make('Demo12345'),
            'role' => 'admin',
            'active' => true,
        ]);

        $barberData = [
            ['name' => 'Andres Mora', 'commission' => 50, 'goal' => 45],
            ['name' => 'Kevin Rojas', 'commission' => 45, 'goal' => 40],
            ['name' => 'Diego Vargas', 'commission' => 50, 'goal' => 50],
        ];

        foreach ($barberData as $index => $data) {
            $barber = Barber::create([
                'name' => $data['name'],
                'commission_percent' => $data['commission'],
                'active' => true,
            ]);

            if ($index === 0) {
                User::create([
                    'name' => 'Barbero Demo',
                    'email' => 'barbero@demo.test',
                    'password' => Hash::make('Demo12345'),
                    'role' => 'barbero',
                    'barber_id' => $barber->id,
                    'active' => true,
                ]);
            }
        }

        User::create([
            'name' => 'Recepcion Demo',
            'email' => 'recepcion@demo.test',
            'password' => Hash::make('Demo12345'),
            'role' => 'recepcion',
            'active' => true,
        ]);
    }

    private function createServices(): array
    {
        $data = [
            ['name' => 'Corte', 'price' => 5000, 'duration_minutes' => 30],
            ['name' => 'Corte + barba', 'price' => 7000, 'duration_minutes' => 60],
            ['name' => 'Barba', 'price' => 3000, 'duration_minutes' => 30],
            ['name' => 'Corte infantil', 'price' => 4000, 'duration_minutes' => 30],
        ];

        $services = [];

        foreach ($data as $item) {
            $services[] = Service::create($item + ['active' => true]);
        }

        return $services;
    }

    private function createClients(): array
    {
        $names = [
            'Jose', 'Carlos', 'Luis', 'Andres', 'Daniel',
            'Gabriel', 'Miguel', 'David', 'Steven', 'Kevin',
            'Fernando', 'Jorge', 'Marco', 'Adrian', 'Pablo',
        ];

        $lastNames = [
            'Rodriguez', 'Vargas', 'Mora', 'Rojas', 'Castro',
            'Salazar', 'Jimenez', 'Gonzalez', 'Ramirez', 'Sanchez',
            'Alvarado', 'Chaves', 'Mendez', 'Campos', 'Solano',
        ];

        $clients = [];

        for ($i = 0; $i < 60; $i++) {
            $clients[] = Client::create([
                'name' => $names[$i % count($names)],
                'last_name' => $lastNames[($i * 7) % count($lastNames)],
                'phone' => (string) (60000000 + $i),
                'notes' => 'Cliente ficticio de demostracion.',
            ]);
        }

        return $clients;
    }

    private function createAppointments(
        Carbon $start,
        Carbon $end,
        array $services,
        array $clients
    ): void {
        $barbers = Barber::orderBy('id')->get();
        $admin = User::where('email', 'admin@demo.test')->firstOrFail();

        $slots = ['10:00', '11:30', '14:00', '16:30'];
        $paymentMethods = ['efectivo', 'sinpe', 'tarjeta'];
        $clientIndex = 0;
        $appointmentIndex = 0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            // El negocio permanece cerrado los domingos.
            if ($date->dayOfWeek === Carbon::SUNDAY) {
                continue;
            }

            foreach ($barbers as $barberIndex => $barber) {
                $count = 2 + (($date->dayOfYear + $barberIndex) % 3);

                for ($slotIndex = 0; $slotIndex < $count; $slotIndex++) {
                    $client = $clients[$clientIndex % count($clients)];
                    $clientIndex++;

                    $service = $services[
                        ($appointmentIndex + $barberIndex) % count($services)
                    ];

                    $startsAt = Carbon::parse(
                        $date->toDateString() . ' ' . $slots[$slotIndex]
                    );

                    $endsAt = $startsAt->copy()->addMinutes(
                        $service->duration_minutes
                    );

                    if ($startsAt->greaterThan(now())) {
                        $status = 'pendiente';
                    } else {
                        $outcome = ($appointmentIndex + $date->dayOfYear) % 10;

                        $status = match (true) {
                            $outcome < 8 => 'completada',
                            $outcome === 8 => 'cancelada',
                            default => 'no_asistio',
                        };
                    }

                    $price = (int) $service->price;
                    $commission = (float) $barber->commission_percent;

                    $tip = $status === 'completada'
                        ? [0, 500, 1000][($appointmentIndex + 1) % 3]
                        : 0;

                    $paymentMethod = $status === 'completada'
                        ? $paymentMethods[$appointmentIndex % 3]
                        : null;

                    $barberAmount = $status === 'completada'
                        ? (int) round($price * $commission / 100)
                        : 0;

                    $appointment = Appointment::create([
                        'barber_id' => $barber->id,
                        'service_id' => $service->id,
                        'client_id' => $client->id,
                        'client_name' => $client->name,
                        'client_last_name' => $client->last_name,
                        'phone' => $client->phone,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'price' => $price,
                        'commission_percent' => $commission,
                        'status' => $status,
                        'barber_amount' => $barberAmount,
                        'owner_amount' => $status === 'completada'
                            ? $price - $barberAmount
                            : 0,
                        'completed_at' => $status === 'completada'
                            ? $endsAt
                            : null,
                        'payment_method' => $paymentMethod,
                        'tip' => $tip,
                        'notes' => 'Cita ficticia para demostracion.',
                    ]);

                    if ($paymentMethod === 'sinpe') {
                        $reference = 'DEMO-' . str_pad(
                            (string) ($appointmentIndex + 1),
                            6,
                            '0',
                            STR_PAD_LEFT
                        );

                        $path = $this->createDemoProof(
                            $appointment,
                            $reference
                        );

                        $appointment->update([
                            'proof_path' => $path,
                            'sinpe_ref' => $reference,
                        ]);
                    }

                    $appointmentIndex++;
                }
            }
        }
    }

    private function createDemoProof(
        Appointment $appointment,
        string $reference
    ): string {
        if (!function_exists('imagecreatetruecolor')) {
            throw new RuntimeException(
                'La extension GD es necesaria para generar los comprobantes demo.'
            );
        }

        $image = imagecreatetruecolor(900, 600);

        if (!$image) {
            throw new RuntimeException('No se pudo generar la imagen demo.');
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $dark = imagecolorallocate($image, 35, 45, 55);
        $green = imagecolorallocate($image, 20, 135, 85);
        $light = imagecolorallocate($image, 235, 247, 240);
        $gray = imagecolorallocate($image, 110, 120, 130);

        imagefilledrectangle($image, 0, 0, 900, 600, $white);
        imagefilledrectangle($image, 0, 0, 900, 125, $light);

        imagestring($image, 5, 40, 35, 'DATOS DE PRUEBA', $green);
        imagestring($image, 5, 40, 165, 'COMPROBANTE SINPE DEMO', $dark);

        imagestring(
            $image,
            5,
            40,
            235,
            'Referencia: ' . $reference,
            $dark
        );

        imagestring(
            $image,
            5,
            40,
            285,
            'Monto: CRC ' . number_format($appointment->price, 0, ',', '.'),
            $dark
        );

        imagestring(
            $image,
            5,
            40,
            335,
            'Fecha: ' . $appointment->starts_at->format('d/m/Y'),
            $dark
        );

        imagestring(
            $image,
            4,
            40,
            430,
            'Imagen ficticia. No representa una transferencia real.',
            $gray
        );

        imagestring($image, 5, 40, 510, 'DATOS DE PRUEBA - SIN VALIDEZ BANCARIA', $green);

        ob_start();
        imagejpeg($image, null, 88);
        $contents = ob_get_clean();
        imagedestroy($image);

        if (!$contents) {
            throw new RuntimeException('No se pudo guardar el comprobante demo.');
        }

        $path = 'proofs-demo/' . now()->format('Y/m') . '/' . Str::uuid() . '.jpg';

        Storage::disk('local')->put($path, $contents);

        return $path;
    }

    private function createExpenses(Carbon $start, Carbon $end): void
    {
        $admin = User::where('email', 'admin@demo.test')->firstOrFail();

        $expenses = [
            ['category' => 'servicios', 'concept' => 'Pago de electricidad', 'amount' => 8500, 'from_cash' => true],
            ['category' => 'productos', 'concept' => 'Compra de productos para cabello', 'amount' => 12000, 'from_cash' => true],
            ['category' => 'mantenimiento', 'concept' => 'Mantenimiento de equipo', 'amount' => 6500, 'from_cash' => false],
            ['category' => 'publicidad', 'concept' => 'Publicidad en redes sociales', 'amount' => 5000, 'from_cash' => true],
            ['category' => 'servicios', 'concept' => 'Pago de internet', 'amount' => 9500, 'from_cash' => false],
            ['category' => 'otros', 'concept' => 'Materiales de limpieza', 'amount' => 3500, 'from_cash' => true],
        ];

        $index = 0;

        for ($date = $start->copy(); $date->lte($end); $date->addDays(5)) {
            if ($date->dayOfWeek === Carbon::SUNDAY) {
                continue;
            }

            $item = $expenses[$index % count($expenses)];

            Expense::create($item + [
                'date' => $date->toDateString(),
                'user_id' => $admin->id,
            ]);

            $index++;
        }
    }

    private function createCashEntries(Carbon $start, Carbon $end): void
    {
        $admin = User::where('email', 'admin@demo.test')->firstOrFail();

        CashEntry::create([
            'date' => $start->toDateString(),
            'type' => 'entrada',
            'amount' => 30000,
            'concept' => 'Fondo inicial de caja demo',
            'user_id' => $admin->id,
        ]);

        $index = 0;

        for ($date = $start->copy()->addDays(9); $date->lte($end); $date->addDays(9)) {
            if ($date->dayOfWeek === Carbon::SUNDAY) {
                continue;
            }

            CashEntry::create([
                'date' => $date->toDateString(),
                'type' => $index % 2 === 0 ? 'entrada' : 'salida',
                'amount' => $index % 2 === 0 ? 10000 : 4000,
                'concept' => $index % 2 === 0
                    ? 'Aporte ficticio de caja'
                    : 'Retiro ficticio de caja',
                'user_id' => $admin->id,
            ]);

            $index++;
        }
    }

    private function createWeeklyPayouts(Carbon $start, Carbon $end): void
    {
        $admin = User::where('email', 'admin@demo.test')->firstOrFail();
        $barbers = Barber::orderBy('id')->get();

        $week = $start->copy()->startOfWeek(Carbon::SUNDAY);

        while ($week->copy()->addDays(6)->lt(today())) {
            $weekEnd = $week->copy()->addDays(6)->endOfDay();

            foreach ($barbers as $index => $barber) {
                $appointments = Appointment::where('barber_id', $barber->id)
                    ->where('status', 'completada')
                    ->whereBetween('starts_at', [
                        $week->copy()->startOfDay(),
                        $weekEnd,
                    ])
                    ->get();

                $amount = (int) $appointments->sum(
                    fn ($appointment) =>
                        $appointment->barber_amount + $appointment->tip
                );

                if ($amount <= 0) {
                    continue;
                }

                WeekPayout::create([
                    'week_start' => $week->toDateString(),
                    'barber_id' => $barber->id,
                    'amount' => $amount,
                    'method' => 'sinpe',
                    'paid_on' => $week->copy()->addDays(6)->toDateString(),
                    'user_id' => $admin->id,
                ]);
            }

            $week->addWeek();
        }
    }

    private function createCashClosings(Carbon $start, Carbon $end): void
    {
        $admin = User::where('email', 'admin@demo.test')->firstOrFail();
        $book = app(\App\Services\CashBook::class);

        for ($date = $start->copy()->startOfDay(); $date->lte($end); $date->addDay()) {
            if ($date->dayOfWeek === Carbon::SUNDAY) {
                continue;
            }

            $figures = $book->day($date);
            $moves = $book->moves($date);

            $opening = $book->opening($date);

            $cashSales = (int) $figures['byMethod']['efectivo']['amount'];

            $expectedTotal = $opening + $cashSales + $moves['net'];

            if ($expectedTotal < 0) {
                throw new RuntimeException(
                    'El efectivo esperado quedó negativo. Se detuvo la creación de cierres.'
                );
            }

            CashClosing::create([
                'date' => $date->toDateString(),
                'opening_cash' => $opening,
                'cash_moves' => $moves['net'],
                'expected_cash' => $cashSales,
                'expected_sinpe' => (int) $figures['byMethod']['sinpe']['amount'],
                'expected_card' => (int) $figures['byMethod']['tarjeta']['amount'],
                'tips' => (int) $figures['tips'],
                'counted_cash' => $expectedTotal,
                'difference' => 0,
                'notes' => 'Cierre ficticio. DATOS DE PRUEBA.',
                'closed_by' => $admin->id,
            ]);
        }
    }
}
