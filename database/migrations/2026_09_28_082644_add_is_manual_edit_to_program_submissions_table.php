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
        Schema::table('program_submissions', function (Blueprint $table) {
            $table->boolean('is_manual_edit')->default(false)->after('raw_data');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_submissions', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('is_manual_edit');
        });
    }
};
