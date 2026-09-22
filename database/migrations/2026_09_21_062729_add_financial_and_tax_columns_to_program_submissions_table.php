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
        Schema::table('program_submissions', function (Blueprint $table) {
            $table->decimal('incentive', 15, 2)->nullable()->after('tax_invoice_url');
            $table->decimal('dpp', 15, 2)->nullable()->after('incentive');
            $table->decimal('dpp_lain', 15, 2)->nullable()->after('dpp');
            $table->decimal('ppn', 15, 2)->nullable()->after('dpp_lain');
            $table->decimal('nilai_pph', 15, 2)->nullable()->after('ppn');
            $table->decimal('net_pay', 15, 2)->nullable()->after('nilai_pph');
            $table->decimal('cek_pajak_tarif_pph', 15, 2)->nullable()->after('net_pay');
            $table->decimal('selisih', 15, 2)->nullable()->after('cek_pajak_tarif_pph');
            $table->string('note_pph', 100)->nullable()->after('selisih');
            $table->string('no_faktur', 100)->nullable()->after('note_pph');
            $table->string('tgl_faktur', 50)->nullable()->after('no_faktur');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'incentive',
                'dpp',
                'dpp_lain',
                'ppn',
                'nilai_pph',
                'net_pay',
                'cek_pajak_tarif_pph',
                'selisih',
                'note_pph',
                'no_faktur',
                'tgl_faktur',
            ]);
        });
    }
};
