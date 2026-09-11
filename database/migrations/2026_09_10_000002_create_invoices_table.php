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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('draft_id')->unique()->constrained('drafts')->cascadeOnDelete();
            $table->string('invoice_type')->index(); // DSA / NPS FL
            $table->string('dealer_code')->nullable();
            $table->string('dealer_name')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('npwp')->nullable();
            $table->string('npwp_name')->nullable();
            $table->string('npwp_type')->nullable();
            $table->string('pph_type')->nullable();
            $table->decimal('support_amount', 15, 2)->default(0);
            $table->decimal('dpp', 15, 2)->default(0);
            $table->decimal('dpp_lain', 15, 2)->default(0);
            $table->decimal('ppn', 15, 2)->default(0);
            $table->decimal('pph', 15, 2)->default(0);
            $table->decimal('netpay', 15, 2)->default(0);
            $table->string('item_code')->nullable();
            $table->string('item_name')->nullable();
            $table->text('address')->nullable();
            $table->string('program_name')->nullable();
            $table->string('program_period')->nullable();
            $table->string('cn_number')->nullable();
            $table->string('invoice_date')->nullable();
            $table->text('ref_note')->nullable();
            $table->string('status')->default('generated')->index();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
