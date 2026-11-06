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
    Schema::table('appointments', function (Blueprint $table) {
        $table->string('proof_path')->nullable()->after('tip');
        $table->string('sinpe_ref', 30)->nullable()->after('proof_path');
    });

    Schema::table('users', function (Blueprint $table) {
        $table->timestamp('last_login_at')->nullable();
        $table->string('last_login_ip', 45)->nullable();
    });
}

public function down(): void
{
    Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['last_login_at', 'last_login_ip']));
    Schema::table('appointments', fn (Blueprint $t) => $t->dropColumn(['proof_path', 'sinpe_ref']));
}
};
