<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Steps of the approval flow of a Data Program row: WhatsApp to telemarketing, the dealer's approval or
     * postponement, WhatsApp to AR, and the cut by AR.
     */
    public function up(): void
    {
        Schema::create('program_approval_logs', function (Blueprint $table) {
            $table->id();
            $table->string('area', 20)->index();
            $table->string('brand', 50)->default('realme')->index();
            $table->foreignId('data_program_id')->nullable()->constrained('data_programs')->cascadeOnDelete();
            $table->foreignId('program_submission_id')->nullable()->constrained('program_submissions')->nullOnDelete();
            $table->string('event', 40);
            $table->boolean('success')->default(true);
            $table->string('actor')->nullable();
            $table->string('recipient')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['data_program_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_approval_logs');
    }
};
