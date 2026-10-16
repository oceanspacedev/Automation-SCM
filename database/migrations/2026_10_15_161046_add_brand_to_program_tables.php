<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables whose rows belong to one brand (existing rows are Realme).
     *
     * @var list<string>
     */
    private const BRAND_TABLES = ['drafts', 'invoices', 'data_programs', 'program_submissions', 'program_reconciliation_logs', 'master_programs'];

    /**
     * Run the migrations.
     *
     * Spreadsheet row identifiers are unique per brand & area (each has its own sheet),
     * and program names & codes are unique per brand (each brand has its own program numbering).
     */
    public function up(): void
    {
        foreach (self::BRAND_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('brand', 50)->default('realme')->after('id')->index();
            });
        }

        foreach (['data_programs', 'program_submissions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['area', 'row_hash']);
                $table->unique(['brand', 'area', 'row_hash']);
            });
        }

        Schema::table('master_programs', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropUnique(['code']);
            $table->unique(['brand', 'name']);
            $table->unique(['brand', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_programs', function (Blueprint $table) {
            $table->dropUnique(['brand', 'name']);
            $table->dropUnique(['brand', 'code']);
            $table->unique('name');
            $table->unique('code');
        });

        foreach (['data_programs', 'program_submissions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['brand', 'area', 'row_hash']);
                $table->unique(['area', 'row_hash']);
            });
        }

        foreach (self::BRAND_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['brand']);
                $table->dropColumn('brand');
            });
        }
    }
};
