<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Cek FP and Cek Evidence hold document links like CN and Agreement. A faktur uploaded through the Form Program
     * is stored under "<dealer>/<program>/<file name>", so its link can be longer than a 255-character string.
     */
    public function up(): void
    {
        Schema::table('data_programs', function (Blueprint $table) {
            $table->text('cek_fp')->nullable()->change();
            $table->text('cek_evidance')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_programs', function (Blueprint $table) {
            $table->string('cek_fp')->nullable()->change();
            $table->string('cek_evidance')->nullable()->change();
        });
    }
};
