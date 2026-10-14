<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master Program, Master Dealer, and reconciliation history are now kept per area (existing rows = Jawa Barat).
     * Program names & codes only need to be unique within an area, since each area has its own program numbering.
     */
    public function up(): void
    {
        Schema::table('master_programs', function (Blueprint $table) {
            $table->string('area', 20)->default('jabar')->after('id')->index();
            $table->dropUnique(['name']);
            $table->dropUnique(['code']);
            $table->unique(['area', 'name']);
            $table->unique(['area', 'code']);
        });

        Schema::table('dealers', function (Blueprint $table) {
            $table->string('area', 20)->default('jabar')->after('id')->index();
        });

        Schema::table('program_reconciliation_logs', function (Blueprint $table) {
            $table->string('area', 20)->default('jabar')->after('id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_reconciliation_logs', function (Blueprint $table) {
            $table->dropIndex(['area']);
            $table->dropColumn('area');
        });

        Schema::table('dealers', function (Blueprint $table) {
            $table->dropIndex(['area']);
            $table->dropColumn('area');
        });

        Schema::table('master_programs', function (Blueprint $table) {
            $table->dropUnique(['area', 'name']);
            $table->dropUnique(['area', 'code']);
            $table->unique('name');
            $table->unique('code');
            $table->dropIndex(['area']);
            $table->dropColumn('area');
        });
    }
};
