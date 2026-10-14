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
        Schema::create('master_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->nullable();
            $table->string('category')->nullable();
            $table->string('period')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed initial default programs
        $now = now();
        $defaults = [
            ['name' => 'PROGRAM DSA FEBRUARI 2026', 'category' => 'DSA', 'period' => 'Februari 2026'],
            ['name' => 'PROGRAM DSA MARET 2026', 'category' => 'DSA', 'period' => 'Maret 2026'],
            ['name' => 'PROGRAM SO R14T MARET 2026', 'category' => 'SO', 'period' => 'Maret 2026'],
            ['name' => 'PROGRAM DSA MEI 2026', 'category' => 'DSA', 'period' => 'Mei 2026'],
            ['name' => 'PROGRAM FS PO C100 & C100X MEI 2026', 'category' => 'FS PO', 'period' => 'Mei 2026'],
            ['name' => 'PROGRAM ST C100 & C100X MEI 2026', 'category' => 'ST', 'period' => 'Mei 2026'],
            ['name' => 'PROGRAM DSA JUNI 2026', 'category' => 'DSA', 'period' => 'Juni 2026'],
            ['name' => 'PROGRAM PROMOTION NOTE 80 4+128 JUNI 2026', 'category' => 'PROMOTION', 'period' => 'Juni 2026'],
            ['name' => 'PROGRAM PROMOTION C100X SERIES JUNI 2026', 'category' => 'PROMOTION', 'period' => 'Juni 2026'],
            ['name' => 'PROGRAM DSA JULI 2026', 'category' => 'DSA', 'period' => 'Juli 2026'],
            ['name' => 'PROGRAM DSA AGUSTUS 2026', 'category' => 'DSA', 'period' => 'Agustus 2026'],
        ];

        foreach ($defaults as $p) {
            DB::table('master_programs')->insertOrIgnore([
                'name' => $p['name'],
                'category' => $p['category'],
                'period' => $p['period'],
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
        Schema::dropIfExists('master_programs');
    }
};
