<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('invoices', 'upload_reminded_at')) {
                    $table->timestamp('upload_reminded_at')->nullable()->after('whatsapp_sent_at');
                }
                if (! Schema::hasColumn('invoices', 'upload_reminder_count')) {
                    $table->unsignedSmallInteger('upload_reminder_count')->default(0)->after('upload_reminded_at');
                }
            });
        }

        if (Schema::hasTable('automation_schedules')) {
            DB::table('automation_schedules')->updateOrInsert(
                ['key' => 'invoice_remind_upload'],
                [
                    'name' => 'Pengingat Upload Dokumen ke Telemarketing',
                    'description' => 'Mengecek invoice yang belum di-upload ke Form Program, lalu mengirim pesan WhatsApp pengingat ke telemarketing per region.',
                    'is_active' => true,
                    'mode' => 'times',
                    'interval_minutes' => null,
                    'times' => json_encode(['09:00', '14:00']),
                    'days' => json_encode([]),
                    'options' => json_encode(['reminder_every_days' => 3, 'reminder_max' => 3]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('automation_schedules')) {
            DB::table('automation_schedules')->where('key', 'invoice_remind_upload')->delete();
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (Schema::hasColumn('invoices', 'upload_reminder_count')) {
                    $table->dropColumn('upload_reminder_count');
                }
                if (Schema::hasColumn('invoices', 'upload_reminded_at')) {
                    $table->dropColumn('upload_reminded_at');
                }
            });
        }
    }
};
