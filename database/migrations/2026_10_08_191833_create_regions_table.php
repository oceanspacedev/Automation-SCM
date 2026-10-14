<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('area', 20)->default('jabar')->index();
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['area', 'name']);
        });

        // Region Jawa Barat yang sudah dipakai di form pengajuan program
        $now = now();
        foreach (['BIG BANDUNG', 'BIG KARAWANG', 'BIG TASIK', 'BIG CIREBON'] as $name) {
            DB::table('regions')->insert([
                'area' => 'jabar',
                'name' => $name,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
