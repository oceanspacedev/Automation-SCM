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
        Schema::create('program_reconciliation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id', 64)->nullable()->index();
            $table->foreignId('data_program_id')->nullable()->constrained('data_programs')->nullOnDelete();
            $table->foreignId('program_submission_id')->nullable()->constrained('program_submissions')->nullOnDelete();
            $table->string('dealer_name')->nullable()->index();
            $table->string('kode_bt', 100)->nullable()->index();
            $table->string('program_name')->nullable()->index();
            $table->string('status', 50)->index(); // MATCHED | DOC_INCOMPLETE | NOMINAL_MISMATCH | NO_MATCH | ERROR
            $table->decimal('dp_amount', 15, 2)->nullable();
            $table->decimal('submission_amount', 15, 2)->nullable();
            $table->decimal('selisih', 15, 2)->nullable();
            $table->string('status_potong_purchase', 50)->nullable();
            $table->string('cek_dokumen', 100)->nullable();
            $table->json('missing_docs')->nullable();
            $table->json('drive_transferred')->nullable();
            $table->text('notes')->nullable();
            $table->string('triggered_by', 50)->default('manual_batch')->index(); // manual_batch | manual_row | auto_submission | auto_sync
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_reconciliation_logs');
    }
};
