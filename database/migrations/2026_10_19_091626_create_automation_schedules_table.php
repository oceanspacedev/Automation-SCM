<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Master Jadwal Otomatis: when each background job runs (every N minutes or at set times, on chosen days).
     * The auto-match job starts as it ran before: every 5 minutes, every day, with WhatsApp to telemarketing.
     */
    public function up(): void
    {
        Schema::create('automation_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('mode', 20)->default('interval');
            $table->unsignedSmallInteger('interval_minutes')->nullable();
            $table->json('times')->nullable();
            $table->json('days')->nullable();
            $table->json('options')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->text('last_result')->nullable();
            $table->timestamps();
        });

        DB::table('automation_schedules')->insert([
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_schedules');
    }
};
