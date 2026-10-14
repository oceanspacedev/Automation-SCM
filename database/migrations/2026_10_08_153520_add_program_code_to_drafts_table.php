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
        Schema::table('drafts', function (Blueprint $table) {
            $table->string('program_code')->nullable()->after('program_name');
        });

        Schema::table('master_programs', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_programs', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });

        Schema::table('drafts', function (Blueprint $table) {
            $table->dropColumn('program_code');
        });
    }
};
