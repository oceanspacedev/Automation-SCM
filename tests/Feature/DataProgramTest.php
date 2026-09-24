<?php

namespace Tests\Feature;

use App\Models\DataProgram;
use App\Models\ProgramSubmission;
use App\Services\DataProgramSyncService;
use App\Services\ProgramReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DataProgramTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_paginated_data_program(): void
    {
        DataProgram::create([
            'row_hash' => 'hash_1',
            'dealer_name' => 'CV OLIVIA',
            'kode_bt' => 'BT03429',
            'program_name' => 'Program Realme 8 Pro',
            'region' => 'BIG CIREBON 2',
            'incentive' => 200000,
            'dpp' => 180180,
            'ppn' => 19820,
            'nilai_pph' => 3604,
            'net_pay' => 196396,
            'status_potong_purchase' => 'SUDAH POTONG',
            'status_potong_ar' => 'SUDAH POTONG',
        ]);

        DataProgram::create([
            'row_hash' => 'hash_2',
            'dealer_name' => 'LIBRA CELL TALUN',
            'kode_bt' => 'BTO5334',
            'program_name' => 'Program Sell Out C11',
            'region' => 'BIG CIREBON 1',
            'incentive' => 150000,
            'net_pay' => 146250,
            'status_potong_purchase' => 'BELUM POTONG',
            'status_potong_ar' => 'BELUM POTONG',
        ]);

        $response = $this->getJson('/api/data-program');

        $response->assertOk()
            ->assertJsonStructure([
                'items',
                'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
                'summary' => ['total_rows', 'total_net_pay', 'total_incentive'],
                'filter_options',
            ]);

        $this->assertEquals(2, $response->json('pagination.total'));
        $this->assertEquals(342646, $response->json('summary.total_net_pay'));
    }

    public function test_can_filter_data_program_by_search_and_region(): void
    {
        DataProgram::create([
            'row_hash' => 'hash_1',
            'dealer_name' => 'CV OLIVIA',
            'kode_bt' => 'BT03429',
            'program_name' => 'Program Realme 8 Pro',
            'region' => 'BIG CIREBON 2',
            'net_pay' => 196396,
        ]);

        DataProgram::create([
            'row_hash' => 'hash_2',
            'dealer_name' => 'LIBRA CELL TALUN',
            'kode_bt' => 'BTO5334',
            'program_name' => 'Program Sell Out C11',
            'region' => 'BIG KARAWANG',
            'net_pay' => 146250,
        ]);

        $res1 = $this->getJson('/api/data-program?search=OLIVIA');
        $res1->assertOk();
        $this->assertEquals(1, $res1->json('pagination.total'));
        $this->assertEquals('CV OLIVIA', $res1->json('items.0.dealer_name'));

        $res2 = $this->getJson('/api/data-program?region=BIG KARAWANG');
        $res2->assertOk();
        $this->assertEquals(1, $res2->json('pagination.total'));
        $this->assertEquals('LIBRA CELL TALUN', $res2->json('items.0.dealer_name'));
    }

    public function test_amount_parser_correctly_handles_indonesian_formats(): void
    {
        $service = new DataProgramSyncService;

        $this->assertEquals(200000.0, $service->parseAmount('  200.000 '));
        $this->assertEquals(180180.0, $service->parseAmount('180.180'));
        $this->assertEquals(3604.0, $service->parseAmount('3.604'));
        $this->assertEquals(196396.0, $service->parseAmount('196.396'));
        $this->assertEquals(0.0, $service->parseAmount('-'));
        $this->assertEquals(0.0, $service->parseAmount(''));
        $this->assertEquals(0.0, $service->parseAmount('#REF!'));
        $this->assertEquals(-50000.0, $service->parseAmount('(50.000)'));
    }

    public function test_can_export_data_program_to_csv(): void
    {
        DataProgram::create([
            'row_hash' => 'hash_1',
            'dealer_name' => 'CV OLIVIA',
            'kode_bt' => 'BT03429',
            'program_name' => 'Program Realme 8 Pro',
            'region' => 'BIG CIREBON 2',
            'net_pay' => 196396,
        ]);

        $response = $this->get('/api/data-program/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('NAMA DEALER', $response->streamedContent());
        $this->assertStringContainsString('CV OLIVIA', $response->streamedContent());
    }

    public function test_can_receive_webhook_single_row(): void
    {
        $payload = [
            'row_index' => 5,
            'values' => [
                'DEALER TESTING REALTIME', // 0. Dealer
                'Program Realme C55',      // 1. Program
                'BT99999',                 // 2. Kode BT
                'Realme C55 Cashback',     // 3. Program Name
                'JAN-FEB 2026',            // 4. Periode
                'BIG JAKARTA',             // 5. Region
                'PO-001',                  // 6. No PO
                'GS-100',                  // 7. ID GS
                'SUPP-01',                 // 8. Kode Supplier
                'ACTIVE',                  // 9. Status DL
                'Budi',                    // 10. Sales Person
                'Siti',                    // 11. Telemarketing
                'YA',                      // 12. Wajib Pajak
                '2%',                      // 13. Trf PPh
                '500.000',                 // 14. Incentive
                '450.000',                 // 15. DPP
                '0',                       // 16. DPP Lain
                '49.500',                  // 17. PPN
                '9.000',                   // 18. Nilai PPh
                '490.500',                 // 19. Net Pay
                'SESUAI',                  // 20. Cek Pajak
                '0',                       // 21. Selisih
                'OK',                      // 22. Note PPh
                '010.000-26.00000001',     // 23. No Faktur Pajak
            ],
        ];

        $response = $this->postJson('/api/webhooks/data-program', $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'saved_count' => 1,
            ]);

        $this->assertDatabaseHas('data_programs', [
            'dealer_name' => 'DEALER TESTING REALTIME',
            'kode_bt' => 'BT99999',
            'program_name' => 'Realme C55 Cashback',
            'region' => 'BIG JAKARTA',
            'net_pay' => 490500.0,
        ]);
    }

    public function test_can_receive_webhook_batch_rows(): void
    {
        $payload = [
            'start_row' => 10,
            'rows' => [
                [
                    'DEALER BATCH 1', 'Program A', 'BT111', 'Prog Name A',
                    '2026', 'BIG BANDUNG', '', '', '', '', '', '', '', '',
                    '100.000', '90.000', '0', '9.900', '1.800', '98.100',
                ],
                [
                    'DEALER BATCH 2', 'Program B', 'BT222', 'Prog Name B',
                    '2026', 'BIG SURABAYA', '', '', '', '', '', '', '', '',
                    '200.000', '180.000', '0', '19.800', '3.600', '196.200',
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/data-program', $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'saved_count' => 2,
            ]);

        $this->assertDatabaseHas('data_programs', [
            'dealer_name' => 'DEALER BATCH 1',
            'kode_bt' => 'BT111',
        ]);
        $this->assertDatabaseHas('data_programs', [
            'dealer_name' => 'DEALER BATCH 2',
            'kode_bt' => 'BT222',
        ]);
    }

    public function test_can_sync_from_google_apps_script_web_app_url(): void
    {
        Http::fake([
            'script.google.com/macros/s/*' => Http::response([
                'success' => true,
                'total' => 1,
                'rows' => [
                    ['Header Dealer', 'Header Program', 'Header BT', 'Header Prog Name'],
                    [
                        'DEALER WEBAPP TEST', 'Program C', 'BT333', 'Prog Name C',
                        '2026', 'BIG MEDAN', '', '', '', '', '', '', '', '',
                        '300.000', '270.000', '0', '29.700', '5.400', '294.300',
                    ],
                ],
            ], 200),
        ]);

        $service = app(DataProgramSyncService::class);
        $result = $service->sync('https://script.google.com/macros/s/AKfycbzXBZOrWjaxN2F_JYslnurQB3FMgbZNysW3ZhXZRqyQMcmUTIIYWghFln43o6iU7YhW/exec');

        $this->assertEquals(1, $result['synced_count']);
        $this->assertDatabaseHas('data_programs', [
            'dealer_name' => 'DEALER WEBAPP TEST',
            'kode_bt' => 'BT333',
            'program_name' => 'Prog Name C',
            'region' => 'BIG MEDAN',
        ]);
    }

    public function test_can_reconcile_data_program_with_form_program(): void
    {
        DataProgram::create([
            'row_hash' => 'hash_reconcile_dp',
            'dealer_name' => 'CV MAJU JAYA',
            'kode_bt' => 'BT999',
            'program_name' => 'PROGRAM REALME AGUSTUS 2026',
            'periode' => 'Agustus 2026',
            'net_pay' => 1500000,
            'dpp' => 1350000,
            'cn' => null,
            'agrement' => null,
            'cek_fp' => null,
            'status_potong_purchase' => null,
        ]);

        ProgramSubmission::create([
            'row_hash' => 'hash_reconcile_sub',
            'dealer_name' => 'MAJU JAYA',
            'program_name' => 'PROGRAM REALME AGUSTUS 2026',
            'net_pay' => 1500000,
            'dpp' => 1350000,
            'credit_note_url' => 'https://drive.google.com/open?id=test_cn_id',
            'agreement_url' => 'https://drive.google.com/open?id=test_agr_id',
            'tax_invoice_url' => 'https://drive.google.com/open?id=test_fp_id',
            'submission_timestamp' => '2026-08-15 10:00:00',
        ]);

        $response = $this->postJson('/api/data-program/reconcile', [
            'year' => '2026',
            'limit' => 10,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('data_programs', [
            'dealer_name' => 'CV MAJU JAYA',
            'cn' => 'https://drive.google.com/open?id=test_cn_id',
            'agrement' => 'https://drive.google.com/open?id=test_agr_id',
            'cek_fp' => 'https://drive.google.com/open?id=test_fp_id',
            'status_potong_purchase' => 'BISA DI POTONG',
            'cek_dokumen' => 'LENGKAP',
        ]);
    }

    public function test_can_update_data_program_row_and_push_to_spreadsheet(): void
    {
        Http::fake([
            'script.google.com/macros/s/*' => Http::response([
                'success' => true,
                'updated_count' => 1,
            ], 200),
        ]);

        $dp = DataProgram::create([
            'row_hash' => 'hash_test_update_dp',
            'dealer_name' => 'DEALER TEST UPDATE',
            'kode_bt' => 'BT-UPDATE',
            'program_name' => 'PROGRAM UPDATE 2026',
            'status_potong_purchase' => null,
            'keterangan' => null,
        ]);

        $response = $this->putJson("/api/data-program/{$dp->id}", [
            'status_potong_purchase' => 'BISA DI POTONG',
            'keterangan' => 'Manual verification done',
            'push_to_sheet' => true,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('data_programs', [
            'id' => $dp->id,
            'status_potong_purchase' => 'BISA DI POTONG',
            'keterangan' => 'Manual verification done',
        ]);
    }

    public function test_reconciliation_fails_when_region_differs(): void
    {
        $dp = DataProgram::create([
            'row_hash' => 'hash_reg_diff_dp',
            'dealer_name' => 'PT KOSAMBI DISTRINDO',
            'kode_bt' => 'BT03818',
            'program_name' => 'Cashback C11 Juli 2026',
            'region' => 'BIG KARAWANG 1',
            'net_pay' => 1000000,
            'dpp' => 900000,
        ]);

        ProgramSubmission::create([
            'row_hash' => 'hash_reg_diff_sub',
            'dealer_name' => 'PT KOSAMBI DISTRINDO',
            'id_real' => 'BT03818',
            'program_name' => 'Cashback C11 Juli 2026',
            'region' => 'BIG BANDUNG', // Beda Region!
            'net_pay' => 1000000,
            'dpp' => 900000,
            'credit_note_url' => 'https://drive.google.com/open?id=test_cn',
            'agreement_url' => 'https://drive.google.com/open?id=test_agr',
            'tax_invoice_url' => 'https://drive.google.com/open?id=test_fp',
        ]);

        $service = app(ProgramReconciliationService::class);
        $result = $service->reconcileSingle($dp);

        $this->assertFalse($result['success']);
        $this->assertNull($dp->fresh()->cn);
    }

    public function test_reconciliation_fails_when_kode_bt_differs(): void
    {
        $dp = DataProgram::create([
            'row_hash' => 'hash_bt_diff_dp',
            'dealer_name' => 'PT KOSAMBI DISTRINDO',
            'kode_bt' => 'BT03818',
            'program_name' => 'Cashback C11 Juli 2026',
            'region' => 'BIG KARAWANG 1',
            'net_pay' => 1000000,
            'dpp' => 900000,
        ]);

        ProgramSubmission::create([
            'row_hash' => 'hash_bt_diff_sub',
            'dealer_name' => 'PT KOSAMBI DISTRINDO',
            'id_real' => 'BT99999', // Beda BT!
            'program_name' => 'Cashback C11 Juli 2026',
            'region' => 'BIG KARAWANG',
            'net_pay' => 1000000,
            'dpp' => 900000,
            'credit_note_url' => 'https://drive.google.com/open?id=test_cn',
            'agreement_url' => 'https://drive.google.com/open?id=test_agr',
            'tax_invoice_url' => 'https://drive.google.com/open?id=test_fp',
        ]);

        $service = app(ProgramReconciliationService::class);
        $result = $service->reconcileSingle($dp);

        $this->assertFalse($result['success']);
        $this->assertNull($dp->fresh()->cn);
    }

    public function test_reconciliation_fails_when_dealer_name_differs_even_with_same_kode_bt(): void
    {
        $dp = DataProgram::create([
            'row_hash' => 'hash_dealer_diff_dp',
            'dealer_name' => 'M2 CELL', // Beda Dealer!
            'kode_bt' => 'BT03818',
            'program_name' => 'Cashback C11 Juli 2026',
            'region' => 'BIG KARAWANG 1',
            'net_pay' => 1000000,
            'dpp' => 900000,
        ]);

        ProgramSubmission::create([
            'row_hash' => 'hash_dealer_diff_sub',
            'dealer_name' => 'PT KOSAMBI DISTRINDO',
            'id_real' => 'BT03818',
            'program_name' => 'Cashback C11 Juli 2026',
            'region' => 'BIG KARAWANG',
            'net_pay' => 1000000,
            'dpp' => 900000,
            'credit_note_url' => 'https://drive.google.com/open?id=test_cn',
            'agreement_url' => 'https://drive.google.com/open?id=test_agr',
            'tax_invoice_url' => 'https://drive.google.com/open?id=test_fp',
        ]);

        $service = app(ProgramReconciliationService::class);
        $result = $service->reconcileSingle($dp);

        $this->assertFalse($result['success']);
        $this->assertNull($dp->fresh()->cn);
    }

    public function test_reconciliation_fails_when_program_name_differs(): void
    {
        $dp = DataProgram::create([
            'row_hash' => 'hash_prog_diff_dp',
            'dealer_name' => 'PT KOSAMBI DISTRINDO',
            'kode_bt' => 'BT03818',
            'program_name' => 'Price Protection Realme 12 5G', // Beda Program!
            'region' => 'BIG KARAWANG 1',
            'net_pay' => 1000000,
            'dpp' => 900000,
        ]);

        ProgramSubmission::create([
            'row_hash' => 'hash_prog_diff_sub',
            'dealer_name' => 'PT KOSAMBI DISTRINDO',
            'id_real' => 'BT03818',
            'program_name' => 'Cashback Sell Out C11',
            'region' => 'BIG KARAWANG',
            'net_pay' => 1000000,
            'dpp' => 900000,
            'credit_note_url' => 'https://drive.google.com/open?id=test_cn',
            'agreement_url' => 'https://drive.google.com/open?id=test_agr',
            'tax_invoice_url' => 'https://drive.google.com/open?id=test_fp',
        ]);

        $service = app(ProgramReconciliationService::class);
        $result = $service->reconcileSingle($dp);

        $this->assertFalse($result['success']);
        $this->assertNull($dp->fresh()->cn);
    }

    public function test_reconciliation_succeeds_when_all_four_criteria_match(): void
    {
        $dp = DataProgram::create([
            'row_hash' => 'hash_all_match_dp',
            'dealer_name' => 'PT KOSAMBI DISTRINDO',
            'kode_bt' => 'BT03818',
            'program_name' => 'Program Cashback Sell Out C11 (21 - 31 JULI 2026)',
            'region' => 'BIG KARAWANG 1',
            'net_pay' => 1000000,
            'dpp' => 900000,
        ]);

        ProgramSubmission::create([
            'row_hash' => 'hash_all_match_sub',
            'dealer_name' => 'PT KOSAMBI DISTRINDO',
            'id_real' => 'BT03818',
            'program_name' => 'Program Cashback Sell Out C11 (21 - 31 JULI 2026)',
            'region' => 'BIG KARAWANG',
            'net_pay' => 1000000,
            'dpp' => 900000,
            'credit_note_url' => 'https://drive.google.com/open?id=test_cn',
            'agreement_url' => 'https://drive.google.com/open?id=test_agr',
            'tax_invoice_url' => 'https://drive.google.com/open?id=test_fp',
        ]);

        $service = app(ProgramReconciliationService::class);
        $result = $service->reconcileSingle($dp);

        $this->assertTrue($result['success']);
        $this->assertEquals('BISA DI POTONG', $result['status']);
        $this->assertEquals('LENGKAP', $result['cek_dokumen']);
        $this->assertEquals('https://drive.google.com/open?id=test_cn', $dp->fresh()->cn);
        $this->assertEquals('https://drive.google.com/open?id=test_agr', $dp->fresh()->agrement);
        $this->assertEquals('https://drive.google.com/open?id=test_fp', $dp->fresh()->cek_fp);
    }

    public function test_reconcile_from_submission_succeeds_and_respects_strict_criteria(): void
    {
        Http::fake([
            'script.google.com/macros/s/*' => Http::response(['success' => true], 200),
        ]);

        $dp = DataProgram::create([
            'row_hash' => 'hash_reconcile_from_sub_dp',
            'dealer_name' => 'PT KOSAMBI DISTRINDO',
            'kode_bt' => 'BT03818',
            'program_name' => 'Program Cashback Sell Out C11 (21 - 31 JULI 2026)',
            'region' => 'BIG KARAWANG 1',
            'net_pay' => 1000000,
            'dpp' => 900000,
        ]);

        $sub = ProgramSubmission::create([
            'row_hash' => 'hash_reconcile_from_sub_sub',
            'dealer_name' => 'PT KOSAMBI DISTRINDO',
            'id_real' => 'BT03818',
            'program_name' => 'Program Cashback Sell Out C11 (21 - 31 JULI 2026)',
            'region' => 'BIG KARAWANG',
            'net_pay' => 1000000,
            'dpp' => 900000,
            'credit_note_url' => 'https://drive.google.com/open?id=test_cn_from_sub',
            'agreement_url' => 'https://drive.google.com/open?id=test_agr_from_sub',
            'tax_invoice_url' => 'https://drive.google.com/open?id=test_fp_from_sub',
        ]);

        $service = app(ProgramReconciliationService::class);
        $matchedDp = $service->reconcileFromSubmission($sub);

        $this->assertNotNull($matchedDp);
        $this->assertEquals($dp->id, $matchedDp->id);
        $this->assertEquals('BISA DI POTONG', $matchedDp->status_potong_purchase);
        $this->assertEquals('LENGKAP', $matchedDp->cek_dokumen);
        $this->assertEquals('https://drive.google.com/open?id=test_cn_from_sub', $dp->fresh()->cn);
    }

    public function test_non_pkp_can_be_cut_with_only_cn_and_agr_without_faktur(): void
    {
        $dp = DataProgram::create([
            'row_hash' => 'hash_non_pkp_dp',
            'dealer_name' => 'LIBRA CELL TALUN',
            'kode_bt' => 'BT01111',
            'program_name' => 'Program Sell Out C11',
            'region' => 'BIG CIREBON 1',
            'wajib_pajak' => 'ORANG PRIBADI',
            'ppn' => 0,
            'net_pay' => 500000,
            'dpp' => 500000,
        ]);

        $sub = ProgramSubmission::create([
            'row_hash' => 'hash_non_pkp_sub',
            'dealer_name' => 'LIBRA CELL TALUN',
            'id_real' => 'BT01111',
            'program_name' => 'Program Sell Out C11',
            'region' => 'BIG CIREBON 1',
            'net_pay' => 500000,
            'dpp' => 500000,
            'credit_note_url' => 'https://drive.google.com/open?id=test_cn_non_pkp',
            'agreement_url' => 'https://drive.google.com/open?id=test_agr_non_pkp',
            'tax_invoice_url' => null, // Non PKP tidak ada faktur pajak
        ]);

        $service = app(ProgramReconciliationService::class);
        $result = $service->reconcileSingle($dp, $sub);

        $this->assertTrue($result['success']);
        $this->assertEquals('BISA DI POTONG', $result['status']);
        $this->assertEquals('LENGKAP', $result['cek_dokumen']);
        $this->assertEquals('https://drive.google.com/open?id=test_cn_non_pkp', $dp->fresh()->cn);
        $this->assertEquals('https://drive.google.com/open?id=test_agr_non_pkp', $dp->fresh()->agrement);
        $this->assertNull($dp->fresh()->cek_fp);
    }

    public function test_pkp_badan_cannot_be_cut_without_faktur(): void
    {
        $dp = DataProgram::create([
            'row_hash' => 'hash_pkp_badan_dp',
            'dealer_name' => 'PT KOSAMBI DISTRINDO',
            'kode_bt' => 'BT03818',
            'program_name' => 'Program Sell Out C11',
            'region' => 'BIG KARAWANG 1',
            'wajib_pajak' => 'BADAN',
            'ppn' => 110000,
            'net_pay' => 1000000,
            'dpp' => 900000,
        ]);

        $sub = ProgramSubmission::create([
            'row_hash' => 'hash_pkp_badan_sub',
            'dealer_name' => 'PT KOSAMBI DISTRINDO',
            'id_real' => 'BT03818',
            'program_name' => 'Program Sell Out C11',
            'region' => 'BIG KARAWANG',
            'net_pay' => 1000000,
            'dpp' => 900000,
            'credit_note_url' => 'https://drive.google.com/open?id=test_cn_badan',
            'agreement_url' => 'https://drive.google.com/open?id=test_agr_badan',
            'tax_invoice_url' => null, // Faktur belum ada
        ]);

        $service = app(ProgramReconciliationService::class);
        $result = $service->reconcileSingle($dp, $sub);

        $this->assertTrue($result['success']);
        $this->assertEquals('BELUM BISA POTONG', $result['status']);
        $this->assertEquals('FAKTUR BELUM ADA', $result['cek_dokumen']);
    }

    public function test_pkp_pribadi_cannot_be_cut_without_faktur(): void
    {
        $dp = DataProgram::create([
            'row_hash' => 'hash_pkp_pribadi_dp',
            'dealer_name' => 'BINTANG CELL',
            'kode_bt' => 'BT02222',
            'program_name' => 'Program Sell Out C11',
            'region' => 'BIG KARAWANG 1',
            'wajib_pajak' => 'PRIBADI PKP',
            'ppn' => 50000,
            'net_pay' => 500000,
            'dpp' => 450000,
        ]);

        $sub = ProgramSubmission::create([
            'row_hash' => 'hash_pkp_pribadi_sub',
            'dealer_name' => 'BINTANG CELL',
            'id_real' => 'BT02222',
            'program_name' => 'Program Sell Out C11',
            'region' => 'BIG KARAWANG',
            'net_pay' => 500000,
            'dpp' => 450000,
            'credit_note_url' => 'https://drive.google.com/open?id=test_cn_pribadi_pkp',
            'agreement_url' => 'https://drive.google.com/open?id=test_agr_pribadi_pkp',
            'tax_invoice_url' => null, // Faktur belum ada
        ]);

        $service = app(ProgramReconciliationService::class);
        $result = $service->reconcileSingle($dp, $sub);

        $this->assertTrue($result['success']);
        $this->assertEquals('BELUM BISA POTONG', $result['status']);
        $this->assertEquals('FAKTUR BELUM ADA', $result['cek_dokumen']);
    }

    public function test_can_reconcile_single_row_via_api(): void
    {
        $dp = DataProgram::create([
            'row_hash' => 'hash_single_row_test_dp',
            'dealer_name' => 'CV SINAR MAKMUR',
            'kode_bt' => 'BT07777',
            'program_name' => 'Program C67 4G',
            'region' => 'BIG BEKASI 1',
            'wajib_pajak' => 'PRIBADI NON PKP',
            'ppn' => 0,
            'net_pay' => 750000,
            'dpp' => 750000,
        ]);

        $sub = ProgramSubmission::create([
            'row_hash' => 'hash_single_row_test_sub',
            'dealer_name' => 'CV SINAR MAKMUR',
            'id_real' => 'BT07777',
            'program_name' => 'Program C67 4G',
            'region' => 'BIG BEKASI',
            'net_pay' => 750000,
            'dpp' => 750000,
            'credit_note_url' => 'https://drive.google.com/open?id=test_single_cn',
            'agreement_url' => 'https://drive.google.com/open?id=test_single_agr',
        ]);

        $response = $this->postJson("/api/data-program/{$dp->id}/reconcile", [
            'force' => true,
            'push_to_sheet' => false,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'matched' => true,
            ])
            ->assertJsonStructure([
                'success',
                'matched',
                'message',
                'data',
                'details' => [
                    'submission_id',
                    'status_potong_purchase',
                    'cek_dokumen',
                    'is_pkp',
                    'criteria_match' => ['region', 'kode_bt', 'dealer', 'program', 'financial'],
                ],
            ]);

        $this->assertEquals('BISA DI POTONG', $response->json('details.status_potong_purchase'));
        $this->assertEquals('LENGKAP', $response->json('details.cek_dokumen'));
        $this->assertFalse($response->json('details.is_pkp'));
        $this->assertTrue($response->json('details.criteria_match.region'));
        $this->assertTrue($response->json('details.criteria_match.kode_bt'));
        $this->assertTrue($response->json('details.criteria_match.dealer'));
        $this->assertTrue($response->json('details.criteria_match.program'));

        $dpFresh = $dp->fresh();
        $this->assertEquals('BISA DI POTONG', $dpFresh->status_potong_purchase);
        $this->assertEquals('https://drive.google.com/open?id=test_single_cn', $dpFresh->cn);
        $this->assertEquals('https://drive.google.com/open?id=test_single_agr', $dpFresh->agrement);
    }

    public function test_prioritizes_submission_with_complete_faktur_over_incomplete(): void
    {
        $dp = DataProgram::create([
            'row_hash' => 'hash_priority_test_dp',
            'dealer_name' => 'CV PRIMA PRESTASI ABADI',
            'kode_bt' => 'BT03663',
            'program_name' => 'PROGRAM PROMOTION NOTE 80 4+128 JUNI 2026',
            'region' => 'BIG KARAWANG',
            'sales_person' => 'Dimas Aji Pratama',
            'wajib_pajak' => 'BADAN',
            'ppn' => 9910,
            'net_pay' => 98197,
            'dpp' => 90090,
        ]);

        // Older submission with complete Faktur and matching sales person
        $sub1 = ProgramSubmission::create([
            'row_hash' => 'hash_priority_test_sub1',
            'dealer_name' => 'Cv prima prestasi abadi',
            'id_real' => 'BT03663',
            'program_name' => 'PROGRAM PROMOTION NOTE 80 4+128 JUNI 2026',
            'region' => 'BIG KARAWANG',
            'sales_name' => 'Dimas Aji Pratama',
            'net_pay' => 98197,
            'dpp' => 90090,
            'no_faktur' => '040.026-00.354504097',
            'credit_note_url' => 'https://drive.google.com/open?id=cn_complete',
            'agreement_url' => 'https://drive.google.com/open?id=agr_complete',
            'tax_invoice_url' => 'https://drive.google.com/open?id=faktur_complete',
        ]);

        // Newer submission with missing Faktur and different sales person
        $sub2 = ProgramSubmission::create([
            'row_hash' => 'hash_priority_test_sub2',
            'dealer_name' => 'CV. PRIMA PRESTASI ABADI',
            'id_real' => 'BT03663',
            'program_name' => 'PROGRAM PROMOTION NOTE 80 4+128 JUNI 2026',
            'region' => 'BIG KARAWANG',
            'sales_name' => 'MUHAMMAD RISWAN',
            'net_pay' => 98197,
            'dpp' => 90090,
            'credit_note_url' => 'https://drive.google.com/open?id=cn_incomplete',
            'agreement_url' => 'https://drive.google.com/open?id=agr_incomplete',
            'tax_invoice_url' => null, // Faktur missing!
        ]);

        $service = app(ProgramReconciliationService::class);
        $bestSub = $service->findMatchingSubmission($dp);

        // Must pick $sub1 because it has complete documents and matching sales person
        $this->assertEquals($sub1->id, $bestSub->id);

        $result = $service->reconcileSingle($dp, null, true);
        $this->assertEquals('BISA DI POTONG', $result['status']);
        $this->assertEquals('LENGKAP', $result['cek_dokumen']);
        $this->assertEquals('https://drive.google.com/open?id=faktur_complete', $dp->fresh()->cek_fp);
        $this->assertEquals('040.026-00.354504097', $dp->fresh()->no_faktur_pajak);
    }

    public function test_can_send_wa_to_telemarketing_for_data_program(): void
    {
        config([
            'services.wag.token' => 'fake_token',
            'services.wag.url' => 'https://waghub.mekayastudio.com',
            'services.wag.telemarketing_phone' => '081234567890',
        ]);

        Http::fake([
            'https://waghub.mekayastudio.com/api/v1/messages' => Http::response([
                'success' => true,
                'data' => ['id' => 'msg_123', 'provider_message_id' => 'prov_123'],
            ], 200),
        ]);

        $dp = DataProgram::create([
            'row_hash' => 'hash_wa_tm_1',
            'dealer_name' => 'CV PRIMA PRESTASI ABADI',
            'kode_bt' => 'BT03663',
            'program_name' => 'PROGRAM PROMOTION NOTE 80',
            'status_potong_purchase' => 'BISA DI POTONG',
            'net_pay' => 98197,
        ]);

        $response = $this->postJson("/api/data-program/{$dp->id}/send-wa-telemarketing");

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        Http::assertSent(function ($request) {
            return str_contains($request['message']['text'], 'PEMBERITAHUAN KLAIM BISA DIPOTONG')
                && str_contains($request['message']['text'], 'BT03663');
        });
    }

    public function test_telemarketing_can_confirm_setuju_and_forward_to_ar(): void
    {
        config([
            'services.wag.token' => 'fake_token',
            'services.wag.url' => 'https://waghub.mekayastudio.com',
            'services.wag.ar_phone' => '081224290502',
        ]);

        Cache::put(DataProgramSyncService::CACHE_KEY_SPREADSHEET_URL, 'https://script.google.com/macros/s/test_token/exec');

        Http::fake([
            'https://waghub.mekayastudio.com/*' => Http::response([
                'success' => true,
                'data' => ['id' => 'msg_ar_123'],
            ], 200),
            'https://script.google.com/macros/s/*' => Http::response([
                'success' => true,
                'updated_count' => 1,
            ], 200),
        ]);

        $dp = DataProgram::create([
            'row_hash' => 'hash_confirm_tm_1',
            'dealer_name' => 'CV PRIMA PRESTASI ABADI',
            'kode_bt' => 'BT03663',
            'program' => 'PROGRAM 347',
            'program_name' => 'PROGRAM PROMOTION NOTE 80',
            'status_potong_purchase' => 'BISA DI POTONG',
            'net_pay' => 98197,
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'data-programs.confirm',
            now()->addDays(7),
            ['id' => $dp->id, 'role' => 'telemarketing']
        );

        $response = $this->post($signedUrl, [
            'action' => 'setuju',
            'role' => 'telemarketing',
        ]);

        $response->assertOk()
            ->assertViewIs('data_programs.confirmed')
            ->assertSee('Dealer Setuju Dipotong');

        $this->assertEquals('DEALER SETUJU (PROSES AR)', $dp->fresh()->status_potong_ar);

        // WA sent to AR
        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'waghub.mekayastudio.com')) {
                $body = $request->data();
                $text = $body['message']['text'] ?? '';

                return str_contains($text, 'PEMBERITAHUAN POTONG KLAIM (TIM AR)');
            }

            return false;
        });
    }

    public function test_ar_can_confirm_potong_and_update_spreadsheet(): void
    {
        Cache::put(DataProgramSyncService::CACHE_KEY_SPREADSHEET_URL, 'https://script.google.com/macros/s/test_token/exec');

        Http::fake([
            'https://script.google.com/macros/s/*' => Http::response([
                'success' => true,
                'updated_count' => 1,
            ], 200),
        ]);

        $dp = DataProgram::create([
            'row_hash' => 'hash_confirm_ar_1',
            'dealer_name' => 'CV PRIMA PRESTASI ABADI',
            'kode_bt' => 'BT03663',
            'program' => 'PROGRAM 347',
            'program_name' => 'PROGRAM PROMOTION NOTE 80',
            'status_potong_purchase' => 'BISA DI POTONG',
            'status_potong_ar' => 'DEALER SETUJU (PROSES AR)',
            'net_pay' => 98197,
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'data-programs.confirm',
            now()->addDays(7),
            ['id' => $dp->id, 'role' => 'ar']
        );

        $response = $this->post($signedUrl, [
            'action' => 'potong',
            'role' => 'ar',
        ]);

        $response->assertOk()
            ->assertViewIs('data_programs.confirmed')
            ->assertSee('Pemotongan Selesai Diproses');

        $fresh = $dp->fresh();
        $this->assertEquals('SUDAH POTONG', $fresh->status_potong_purchase);
        $this->assertEquals('DONE', $fresh->status_potong_ar);
        $this->assertNotNull($fresh->tgl_potong_tf);

        // Spreadsheet pushed
        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'script.google.com/macros/s/')) {
                $payload = $request->data();

                return ($payload['action'] ?? '') === 'update_rows'
                    && ($payload['rows'][0]['status_potong_purchase'] ?? '') === 'SUDAH POTONG'
                    && ($payload['rows'][0]['status_potong_ar'] ?? '') === 'DONE';
            }

            return false;
        });
    }
}
