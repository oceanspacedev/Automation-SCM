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
     * The match result note gets its own column so 'noted' keeps the Refnote from the imported Excel.
     * Match notes already written into 'noted' are moved, and 'noted' gets the Refnote of its draft back.
     */
    public function up(): void
    {
        Schema::table('data_programs', function (Blueprint $table) {
            $table->text('match_note')->nullable()->after('noted');
        });

        $matchNoteRows = fn () => DB::table('data_programs')->where(function ($query) {
            $query->where('noted', 'like', 'MATCH Test Program #%')
                ->orWhere('noted', 'like', 'SELISIH Test Program #%');
        });

        $matchNoteRows()->update(['match_note' => DB::raw('noted')]);

        $matchNoteRows()->update([
            'noted' => DB::raw('(SELECT drafts.ref_note FROM drafts WHERE drafts.id = data_programs.draft_id)'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_programs', function (Blueprint $table) {
            $table->dropColumn('match_note');
        });
    }
};
