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
        Schema::create('program_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('submission_timestamp')->nullable();
            $table->string('region')->nullable()->index();
            $table->text('id_real')->nullable();
            $table->text('dealer_name')->nullable();
            $table->text('program_name')->nullable();
            $table->text('sales_name')->nullable();
            $table->text('credit_note_url')->nullable();
            $table->text('agreement_url')->nullable();
            $table->text('tax_invoice_url')->nullable();
            $table->string('row_hash')->unique()->index();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_submissions');
    }
};
