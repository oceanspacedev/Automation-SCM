<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('companies')) {
            DB::table('companies')
                ->where('slug', 'msi')
                ->update([
                    'name' => 'PT MEDIA SELULAR INDONESIA',
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('companies')) {
            DB::table('companies')
                ->where('slug', 'msi')
                ->update([
                    'name' => 'PT MSI',
                    'updated_at' => now(),
                ]);
        }
    }
};
