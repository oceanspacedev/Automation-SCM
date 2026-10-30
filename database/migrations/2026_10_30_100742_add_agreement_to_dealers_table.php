<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A store's agreement: one signed document per store, used for every Realme program,
     * so the Form Program fills it in instead of asking for an upload each time.
     */
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->string('agreement_url', 1000)->nullable()->after('sales_person');
            $table->timestamp('agreement_updated_at')->nullable()->after('agreement_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->dropColumn(['agreement_url', 'agreement_updated_at']);
        });
    }
};
