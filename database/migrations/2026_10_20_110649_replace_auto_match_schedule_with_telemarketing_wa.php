<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Matching now runs every minute on its own, so the only job left in Master Jadwal Otomatis is the
     * WhatsApp to telemarketing. It starts at 09:00 and 14:00 every day.
     */
    public function up(): void
    {
        DB::table('automation_schedules')->where('key', 'auto_match')->delete();

        DB::table('automation_schedules')->insertOrIgnore([
            'key' => 'telemarketing_wa',
            'name' => 'Kirim WA ke telemarketing',
            'description' => 'Mengirim WhatsApp ke telemarketing untuk baris Data Program yang BISA DI POTONG dan belum pernah dikirimi.',
            'is_active' => true,
            'mode' => 'times',
            'interval_minutes' => null,
            'times' => json_encode(['09:00', '14:00']),
            'days' => json_encode([]),
            'options' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('automation_schedules')->where('key', 'telemarketing_wa')->delete();

        DB::table('automation_schedules')->insertOrIgnore([
            'key' => 'auto_match',
            'name' => 'Cocokkan otomatis & WA telemarketing',
            'description' => 'Mencocokkan Data Program dengan Form Program, lalu mengirim WhatsApp ke telemarketing untuk baris yang BISA DI POTONG.',
            'is_active' => true,
            'mode' => 'interval',
            'interval_minutes' => 5,
            'times' => json_encode([]),
            'days' => json_encode([]),
            'options' => json_encode(['send_whatsapp' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
