<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Existing rows belong to Jawa Barat. Spreadsheet row identifiers (row_hash) are now unique per area,
     * because each area syncs its own Google Spreadsheet (row_5 in Jateng is a different row than row_5 in Jabar).
     */
    public function up(): void
    {
        foreach (['data_programs', 'program_submissions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('area', 20)->default('jabar')->after('id')->index();
                $table->dropUnique(['row_hash']);
                $table->unique(['area', 'row_hash']);
            });
        }

        Schema::table('drafts', function (Blueprint $table) {
            $table->string('area', 20)->default('jabar')->after('id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drafts', function (Blueprint $table) {
            $table->dropColumn('area');
        });

        foreach (['data_programs', 'program_submissions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['area', 'row_hash']);
                $table->unique(['row_hash']);
                $table->dropIndex(['area']);
                $table->dropColumn('area');
            });
        }
    }
};
