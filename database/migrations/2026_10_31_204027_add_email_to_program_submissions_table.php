<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The store's email entered in the public Form Program; it also fills the store's email in Master Dealer when
     * that is still empty.
     */
    public function up(): void
    {
        Schema::table('program_submissions', function (Blueprint $table) {
            $table->string('email')->nullable()->after('whatsapp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_submissions', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
