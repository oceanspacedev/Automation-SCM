<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master Dealer now follows the CV TOP master file: one row per store identified by its kode BT
     * ("ID Customer"). The ID Realme ("ID GS") may be empty or shared by several stores.
     */
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->dropUnique(['id_realme']);
        });

        Schema::table('dealers', function (Blueprint $table) {
            $table->string('id_realme')->nullable()->change();
            $table->index('id_realme');
            $table->unique('kode_bt');

            $table->string('alias')->nullable()->after('dealer_name');
            $table->string('gs_name')->nullable()->after('id_realme');
            $table->string('npwp', 50)->nullable()->after('address');
            $table->string('npwp_name')->nullable()->after('npwp');
            $table->string('npwp_type', 50)->nullable()->after('npwp_name');
            $table->string('reffnote')->nullable()->after('npwp_type');
            $table->string('telemarketing')->nullable()->after('reffnote');
            $table->string('telemarketing_phone', 50)->nullable()->after('telemarketing');
            $table->string('sales_person')->nullable()->after('telemarketing_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->dropColumn(['alias', 'gs_name', 'npwp', 'npwp_name', 'npwp_type', 'reffnote', 'telemarketing', 'telemarketing_phone', 'sales_person']);
            $table->dropUnique(['kode_bt']);
            $table->dropIndex(['id_realme']);
        });

        Schema::table('dealers', function (Blueprint $table) {
            $table->string('id_realme')->nullable(false)->change();
            $table->unique('id_realme');
        });
    }
};
