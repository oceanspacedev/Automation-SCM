<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master Program is one list for every area again (Jawa Barat & Jawa Tengah forms show the same programs),
     * so program names and codes are unique across the whole table.
     */
    public function up(): void
    {
        Schema::table('master_programs', function (Blueprint $table) {
            $table->dropUnique(['area', 'name']);
            $table->dropUnique(['area', 'code']);
            $table->dropIndex(['area']);
            $table->dropColumn('area');
        });

        Schema::table('master_programs', function (Blueprint $table) {
            $table->unique('name');
            $table->unique('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_programs', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropUnique(['code']);
            $table->string('area', 20)->default('jabar')->after('id')->index();
            $table->unique(['area', 'name']);
            $table->unique(['area', 'code']);
        });
    }
};
