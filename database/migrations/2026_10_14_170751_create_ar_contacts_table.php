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
        // Master AR: AR yang menerima WA "dealer setuju dipotong" dari telemarketing, satu per region
        Schema::create('ar_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('area', 20)->default('jabar')->index();
            $table->string('region', 100);
            $table->string('name');
            $table->string('whatsapp', 30);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['area', 'region']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ar_contacts');
    }
};
