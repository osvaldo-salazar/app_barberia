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
    Schema::table('barbers', function (Blueprint $table) {
        $table->unsignedSmallInteger('weekly_goal')->nullable()->after('commission_percent');
    });

    Schema::table('appointments', function (Blueprint $table) {
        $table->timestamp('reminded_at')->nullable()->after('sinpe_ref');
    });

    Schema::create('activity_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        $table->string('user_name', 80)->nullable();
        $table->string('action', 20);                   // created, updated, deleted, login, logout, failed, export, security
        $table->string('subject_type', 40)->nullable();
        $table->unsignedBigInteger('subject_id')->nullable();
        $table->text('description');
        $table->string('ip', 45)->nullable();
        $table->timestamp('created_at')->useCurrent();
        $table->index('created_at');
        $table->index(['user_id', 'created_at']);
    });
}

public function down(): void
{
    Schema::dropIfExists('activity_logs');
    Schema::table('appointments', fn (Blueprint $t) => $t->dropColumn('reminded_at'));
    Schema::table('barbers', fn (Blueprint $t) => $t->dropColumn('weekly_goal'));
}
};
