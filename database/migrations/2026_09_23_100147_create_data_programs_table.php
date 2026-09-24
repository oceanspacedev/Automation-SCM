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
        Schema::create('data_programs', function (Blueprint $table) {
            $table->id();
            $table->string('row_hash', 64)->unique();

            // 1. NAMA DEALER
            $table->string('dealer_name')->nullable()->index();
            // 2. PROGRAM
            $table->string('program')->nullable()->index();
            // 3. Kode BT
            $table->string('kode_bt')->nullable()->index();
            // 4. NAMA PROGRAM
            $table->string('program_name')->nullable()->index();
            // 5. PERIODE
            $table->string('periode')->nullable();
            // 6. REGION
            $table->string('region')->nullable()->index();
            // 7. NO PO
            $table->string('no_po')->nullable();
            // 8. ID GS
            $table->string('id_gs')->nullable();
            // 9. KODE SUPPLIER
            $table->string('kode_supplier')->nullable();
            // 10. Status DL
            $table->string('status_dl')->nullable();
            // 11. SALES PERSON
            $table->string('sales_person')->nullable()->index();
            // 12. TELEMARKETING
            $table->string('telemarketing')->nullable();
            // 13. WAJIB PAJAK
            $table->string('wajib_pajak')->nullable();
            // 14. TRF PPH
            $table->string('trf_pph')->nullable();
            // 15. INCENTIVE
            $table->decimal('incentive', 16, 2)->default(0);
            // 16. DPP
            $table->decimal('dpp', 16, 2)->default(0);
            // 17. DPP LAIN
            $table->decimal('dpp_lain', 16, 2)->default(0);
            // 18. PPN
            $table->decimal('ppn', 16, 2)->default(0);
            // 19. NILAI PPH
            $table->decimal('nilai_pph', 16, 2)->default(0);
            // 20. NET PAY
            $table->decimal('net_pay', 16, 2)->default(0);
            // 21. CEK PAJAK TARIF PPH
            $table->string('cek_pajak_tarif')->nullable();
            // 22. SELISIH
            $table->decimal('selisih', 16, 2)->default(0);
            // 23. NOTE PPH
            $table->string('note_pph')->nullable();
            // 24. NO FAKTUR PAJAK
            $table->string('no_faktur_pajak')->nullable();
            // 25. KET FAKTUR PAJAK
            $table->string('ket_faktur_pajak')->nullable();
            // 26. NO PO/SJ
            $table->string('no_po_sj')->nullable();
            // 27. NO TRANSAKSI
            $table->string('no_transaksi')->nullable();
            // 28. Tgl Input
            $table->string('tgl_input')->nullable();
            // 29. Tgl Share CN
            $table->string('tgl_share_cn')->nullable();
            // 30. Lama Pending
            $table->string('lama_pending')->nullable();
            // 31. Keterangan
            $table->string('keterangan')->nullable()->index();
            // 32. CEK DOKUMEN
            $table->string('cek_dokumen')->nullable()->index();
            // 33. STATUS POTONG BY PURCHASE
            $table->string('status_potong_purchase')->nullable()->index();
            // 34. STATUS POTONG BY AR
            $table->string('status_potong_ar')->nullable()->index();
            // 35. TANGGAL POTONG/TF
            $table->string('tgl_potong_tf')->nullable();
            // 36. No. UID
            $table->string('no_uid')->nullable();
            // 37. NO. PEMBAYARAN
            $table->string('no_pembayaran')->nullable();
            // 38. TGL INPUT BANK PPH
            $table->string('tgl_input_bank_pph')->nullable();
            // 39. T/F
            $table->string('tf_status')->nullable();
            // 40. TGL PROSES
            $table->string('tgl_proses')->nullable();
            // 41. TGL SJ
            $table->string('tgl_sj')->nullable();
            // 42. NO.SJ
            $table->string('no_sj')->nullable();
            // 43. INFO BANK
            $table->string('info_bank')->nullable();
            // 44. PENDING POTONGAN
            $table->string('pending_potongan')->nullable();
            // 45. NPWP
            $table->string('npwp')->nullable();
            // 46. NAMA NPWP
            $table->string('nama_npwp')->nullable();
            // 47. PROGRAM 2
            $table->text('program_2')->nullable();
            // 48. CN
            $table->text('cn')->nullable();
            // 49. AGREMENT
            $table->text('agrement')->nullable();
            // 50. CEK FP
            $table->string('cek_fp')->nullable();
            // 51. CEK EVIDANCE
            $table->string('cek_evidance')->nullable();
            // 52. Noted
            $table->text('noted')->nullable();
            // 53. Norek
            $table->string('norek')->nullable();
            // 54. Namrek
            $table->string('namrek')->nullable();
            // 55. Bank
            $table->string('bank')->nullable();
            // 56. BIG REGION
            $table->string('big_region')->nullable()->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_programs');
    }
};
