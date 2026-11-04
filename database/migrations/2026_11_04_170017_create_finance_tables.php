<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('expenses', function (Blueprint $table) {
        $table->id();
        $table->date('date');
        $table->string('category', 20);
        $table->string('concept', 80);
        $table->unsignedInteger('amount');
        $table->boolean('from_cash')->default(false);   // ¿se pagó con plata de la caja?
        $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        $table->timestamps();
        $table->index('date');
    });

    // Movimientos manuales de caja (retiros del jefe, cambio, etc.)
    Schema::create('cash_entries', function (Blueprint $table) {
        $table->id();
        $table->date('date');
        $table->string('type', 10);                     // entrada | salida
        $table->unsignedInteger('amount');
        $table->string('concept', 80);
        $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        $table->timestamps();
        $table->index('date');
    });

        // Pago semanal a cada barbero
        Schema::create('week_payouts', function (Blueprint $table) {
            $table->id();
            $table->date('week_start');                     // domingo de esa semana
            $table->foreignId('barber_id')->constrained();
            $table->unsignedInteger('amount');              // comisión + propinas
            $table->string('method', 10);                   // efectivo | sinpe
            $table->date('paid_on');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['week_start', 'barber_id']);
        });

        Schema::table('cash_closings', function (Blueprint $table) {
            $table->unsignedInteger('opening_cash')->default(0)->after('date');
            $table->integer('cash_moves')->default(0)->after('opening_cash');  // gastos, pagos y retiros netos
        });
    }

        public function down(): void
        {
            Schema::table('cash_closings', function (Blueprint $table) {
                $table->dropColumn(['opening_cash', 'cash_moves']);
            });
            Schema::dropIfExists('week_payouts');
            Schema::dropIfExists('cash_entries');
            Schema::dropIfExists('expenses');
        }
};
