<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * After AR cuts a claim, the tax team gets a WhatsApp to check its PPh and fills in Cek Pajak, Selisih and Note PPh
     * through the link: when that WhatsApp went out, and when the tax team saved the check.
     */
    public function up(): void
    {
        Schema::table('data_programs', function (Blueprint $table) {
            $table->timestamp('tax_notified_at')->nullable()->after('telemarketing_reminder_count');
            $table->timestamp('tax_checked_at')->nullable()->after('tax_notified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_programs', function (Blueprint $table) {
            $table->dropColumn(['tax_notified_at', 'tax_checked_at']);
        });
    }
};
