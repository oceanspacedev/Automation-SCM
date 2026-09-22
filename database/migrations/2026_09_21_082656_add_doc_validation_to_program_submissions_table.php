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
            $table->json('doc_validation')->nullable()->after('tgl_faktur');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_submissions', function (Blueprint $table) {
            $table->dropColumn('doc_validation');
        });
    }
};
