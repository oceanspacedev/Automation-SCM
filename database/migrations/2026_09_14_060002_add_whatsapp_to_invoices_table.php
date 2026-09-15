<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('whatsapp')->nullable()->after('email_sent_at');
            $table->timestamp('whatsapp_sent_at')->nullable()->after('whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['whatsapp', 'whatsapp_sent_at']);
        });
    }
};
