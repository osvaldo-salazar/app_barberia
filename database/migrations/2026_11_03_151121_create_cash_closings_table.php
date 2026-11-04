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
    Schema::create('cash_closings', function (Blueprint $table) {
        $table->id();
        $table->date('date')->unique();
        $table->unsignedInteger('expected_cash')->default(0);
        $table->unsignedInteger('expected_sinpe')->default(0);
        $table->unsignedInteger('expected_card')->default(0);
        $table->unsignedInteger('tips')->default(0);
        $table->unsignedInteger('counted_cash');
        $table->integer('difference');
        $table->text('notes')->nullable();
        $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('cash_closings');
}
};
