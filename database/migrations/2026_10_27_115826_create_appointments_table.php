<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
                Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->string('client_name', 60);
            $table->string('client_last_name', 60);
            $table->string('phone', 8);                          // 8 dígitos de Costa Rica
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('price');                    // precio "congelado" al agendar
            $table->decimal('commission_percent', 5, 2);         // % "congelado" también
            $table->enum('status', ['pendiente', 'completada', 'cancelada', 'no_asistio'])->default('pendiente');
            $table->unsignedInteger('barber_amount')->default(0);
            $table->unsignedInteger('owner_amount')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['barber_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
