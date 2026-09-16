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
            $table->string('no_po_sj')->nullable()->after('tax_invoice_url');
            $table->string('no_transaksi')->nullable()->after('no_po_sj');
            $table->string('tgl_input')->nullable()->after('no_transaksi');
            $table->string('tgl_share_cn')->nullable()->after('tgl_input');
            $table->string('lama_pending')->nullable()->after('tgl_share_cn');
            $table->text('keterangan')->nullable()->after('lama_pending');
            $table->text('cek_dokumen')->nullable()->after('keterangan');
            $table->string('status_potong_purchase')->nullable()->index()->after('cek_dokumen');
            $table->string('status_potong_ar')->nullable()->after('status_potong_purchase');
            $table->string('tgl_potong_tf')->nullable()->after('status_potong_ar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'no_po_sj',
                'no_transaksi',
                'tgl_input',
                'tgl_share_cn',
                'lama_pending',
                'keterangan',
                'cek_dokumen',
                'status_potong_purchase',
                'status_potong_ar',
                'tgl_potong_tf',
            ]);
        });
    }
};
