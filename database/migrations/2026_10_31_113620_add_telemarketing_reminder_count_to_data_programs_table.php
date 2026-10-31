<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * How many reminders the telemarketing got for a row the dealer has not approved yet, so the WhatsApp job stops
     * at the maximum set in Master Jadwal Otomatis. telemarketing_notified_at keeps the time of the last WhatsApp.
     */
    public function up(): void
    {
        Schema::table('data_programs', function (Blueprint $table) {
            $table->unsignedTinyInteger('telemarketing_reminder_count')->default(0)->after('telemarketing_notified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_programs', function (Blueprint $table) {
            $table->dropColumn('telemarketing_reminder_count');
        });
    }
};
