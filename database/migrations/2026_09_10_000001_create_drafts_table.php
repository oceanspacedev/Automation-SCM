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
        Schema::create('drafts', function (Blueprint $table) {
            $table->id();
            $table->string('no')->nullable();
            $table->string('region')->nullable();
            $table->string('rsm')->nullable();
            $table->string('dealer_code')->nullable()->index();
            $table->string('kode_bt')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('dealer_name')->nullable();
            $table->decimal('real_qty', 15, 2)->nullable();
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
            $table->string('invoice_type')->nullable()->index(); // DSA / NPS FL
            $table->string('status')->default('ready')->index(); // ready, error, invoiced
            $table->json('validation_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drafts');
    }
};
