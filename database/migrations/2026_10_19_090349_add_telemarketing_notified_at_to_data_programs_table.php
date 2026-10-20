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
     * When the WhatsApp to telemarketing was sent, so the auto-match job sends it once per row.
     * Rows that could already be cut before this existed count as handled, so a deploy sends no burst of messages.
     */
    public function up(): void
    {
        Schema::table('data_programs', function (Blueprint $table) {
            $table->timestamp('telemarketing_notified_at')->nullable()->after('telemarketing_phone');
        });

        DB::table('data_programs')
            ->where('status_potong_purchase', 'BISA DI POTONG')
            ->update(['telemarketing_notified_at' => DB::raw('updated_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_programs', function (Blueprint $table) {
            $table->dropColumn('telemarketing_notified_at');
        });
    }
};
