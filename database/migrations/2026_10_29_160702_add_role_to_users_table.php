<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('role', 20)->default('recepcion')->after('password');
        $table->foreignId('barber_id')->nullable()->after('role')->constrained()->nullOnDelete();
        $table->boolean('active')->default(true)->after('barber_id');
    });

    // Los usuarios que ya existen (tú) pasan a ser admin
    DB::table('users')->update(['role' => 'admin']);
}


    /**
     * Reverse the migrations.
     */
   public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropConstrainedForeignId('barber_id');
        $table->dropColumn(['role', 'active']);
    });
}
};
