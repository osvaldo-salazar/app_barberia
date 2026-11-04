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
            Schema::create('time_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('barber_id')->nullable()->constrained()->cascadeOnDelete(); // null = toda la barbería
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->boolean('all_day')->default(false);
                $table->string('reason', 80)->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();

                $table->index(['barber_id', 'starts_at']);
            });
        }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_blocks');
    }
};
