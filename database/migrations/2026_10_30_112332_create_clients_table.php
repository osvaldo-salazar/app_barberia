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
    Schema::create('clients', function (Blueprint $table) {
        $table->id();
        $table->string('name', 60);
        $table->string('last_name', 60);
        $table->string('phone', 8)->unique();
        $table->text('notes')->nullable();
        $table->timestamps();
    });

    Schema::table('appointments', function (Blueprint $table) {
        $table->foreignId('client_id')->nullable()->after('service_id')
              ->constrained()->nullOnDelete();
    });

    // Crea la ficha de cada cliente que ya tiene citas (queda el nombre más reciente)
    $map = [];
    $rows = DB::table('appointments')->orderBy('starts_at')
        ->get(['id', 'client_name', 'client_last_name', 'phone']);

    foreach ($rows as $a) {
        if (! isset($map[$a->phone])) {
            $map[$a->phone] = DB::table('clients')->insertGetId([
                'name'       => $a->client_name,
                'last_name'  => $a->client_last_name,
                'phone'      => $a->phone,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('clients')->where('id', $map[$a->phone])->update([
                'name'       => $a->client_name,
                'last_name'  => $a->client_last_name,
                'updated_at' => now(),
            ]);
        }

        DB::table('appointments')->where('id', $a->id)->update(['client_id' => $map[$a->phone]]);
    }
}

public function down(): void
{
    Schema::table('appointments', function (Blueprint $table) {
        $table->dropConstrainedForeignId('client_id');
    });
    Schema::dropIfExists('clients');
}

};
