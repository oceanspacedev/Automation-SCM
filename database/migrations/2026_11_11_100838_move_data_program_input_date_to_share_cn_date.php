<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The date a Data Program row came in from the draft was kept as Tgl Input; it is the Tgl Share CN. Move it there for
 * the rows without a Tgl Share CN, so Tgl Input is left for the day the No PO/SJ is imported.
 * One way: a rollback can't tell the moved dates from Tgl Share CN filled later, so down() leaves the data as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Urutan kolom penting: MySQL mengisi SET dari kiri ke kanan, jadi tgl_share_cn disalin sebelum tgl_input dikosongkan
        DB::table('data_programs')
            ->whereNotNull('tgl_input')
            ->where('tgl_input', '!=', '')
            ->where(fn (Builder $query) => $query->whereNull('tgl_share_cn')->orWhere('tgl_share_cn', ''))
            ->update(['tgl_share_cn' => DB::raw('tgl_input'), 'tgl_input' => null]);
    }

    public function down(): void
    {
        //
    }
};
