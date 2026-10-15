<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Invoices are listed per area like their drafts; existing invoices take the area of their draft.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('area', 20)->default('jabar')->after('id')->index();
        });

        DB::table('invoices')->whereNotNull('draft_id')->update([
            'area' => DB::raw("COALESCE((SELECT drafts.area FROM drafts WHERE drafts.id = invoices.draft_id), 'jabar')"),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['area']);
            $table->dropColumn('area');
        });
    }
};
