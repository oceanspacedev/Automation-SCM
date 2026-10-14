<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sales & telemarketing now come from Master Dealer (per store), so Master Program no longer keeps them.
     */
    public function up(): void
    {
        Schema::table('master_programs', function (Blueprint $table) {
            $table->dropColumn(['sales_person', 'telemarketing', 'telemarketing_phone']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_programs', function (Blueprint $table) {
            $table->string('sales_person')->nullable()->after('period');
            $table->string('telemarketing')->nullable()->after('sales_person');
            $table->string('telemarketing_phone', 50)->nullable()->after('telemarketing');
        });
    }
};
