<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('drafts', function (Blueprint $table) {
            $table->string('dsa')->nullable()->after('rsm');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('dsa')->nullable()->after('rsm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drafts', function (Blueprint $table) {
            $table->dropColumn('dsa');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('dsa');
        });
    }
};
