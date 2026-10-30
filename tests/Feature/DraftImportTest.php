<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\Draft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DraftImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // API selain login, form publik & webhook butuh login
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_can_import_draft_excel_with_success_and_row_errors(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        // 1. Headers matching required format
        $headers = [
            'No.', 'Region', 'RSM', 'Dealer Code', 'Kode BT', 'Nama Cust by CSA',
            'Dealer Name', 'Realqty', 'NPWP', 'Nama NPWP', 'Jenis NPWP', 'Jenis Pph',
            'Support Amount', 'Dpp', 'Dpp Lain', 'Ppn', 'Pph', 'Netpay',
            'KODE ITEM', 'NAMA ITEM', 'Alamat', 'Nama Program', 'Periode Program',
            'No CN', 'Tanggal Inv', 'REFNOTE', 'DSA/NPS FL',
        ];
        $sheet->fromArray([$headers], null, 'A1');

        // 2. Row 1: Valid DSA (BADAN)
        $row1 = [
            '1', 'JABO', 'RSM01', 'DLR001', 'BT01', 'Cust A',
            'PT Dealer A', 10, '01.234.567.8-901.000', 'PT Dealer A', 'BADAN', 'BADAN',
            10000000, 9009009, 8258258, 990991, 180180, 9819820,
            'ITM01', 'Barang Promo A', 'Jl. Merdeka No 1', 'Program Q1', 'Jan-Mar 2026',
            'CN001', '2026-03-01', 'Ref Note 1', 'DSA',
        ];

        // 3. Row 2: Valid NPS FL (PRIBADI)
        $row2 = [
            '2', 'JABO', 'RSM01', 'DLR002', 'BT02', 'Cust B',
            'Toko B', 5, '02.234.567.8-901.000', 'Toko B', 'PRIBADI', 'PRIBADI',
            5000000, 4504505, 0, 0, 112613, 4391892,
            'ITM02', 'Barang Promo B', 'Jl. Sudirman No 2', 'Program Q1', 'Jan-Mar 2026',
            'CN002', '2026-03-02', 'Ref Note 2', 'NPS FL',
        ];

        // 4. Row 3: Invalid DSA/NPS FL (should be error status, but not crash import)
        $row3 = [
            '3', 'JABO', 'RSM01', 'DLR003', 'BT03', 'Cust C',
            'Toko C', 2, '03.234.567.8-901.000', 'Toko C', 'BADAN', 'BADAN',
            2000000, 1801802, 1651652, 198198, 36036, 1963964,
            'ITM03', 'Barang Promo C', 'Jl. Thamrin No 3', 'Program Q1', 'Jan-Mar 2026',
            'CN003', '2026-03-03', 'Ref Note 3', 'UNKNOWN', // Invalid type!
        ];

        $sheet->fromArray([$row1, $row2, $row3], null, 'A2');

        // Save to temporary file
        $tempPath = @tempnam(sys_get_temp_dir(), 'test_draft_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $uploadedFile = new UploadedFile(
            $tempPath,
            'draft.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->postJson('/api/drafts/import', [
            'file' => $uploadedFile,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'total_rows' => 3,
                'success' => 2,
                'failed' => 1,
            ],
        ]);

        // Verify database records
        $this->assertDatabaseCount('drafts', 3);

        $readyDrafts = Draft::where('status', 'ready')->get();
        $this->assertCount(2, $readyDrafts);

        $errorDraft = Draft::where('status', 'error')->first();
        $this->assertNotNull($errorDraft);
        $this->assertEquals('DLR003', $errorDraft->dealer_code);

        @unlink($tempPath);
    }

    public function test_import_marks_rows_with_empty_or_already_used_cn_number_as_error(): void
    {
        Draft::create(['area' => 'jateng', 'dealer_name' => 'TOKO LAMA', 'support_amount' => 1000, 'cn_number' => '2608000045', 'status' => 'ready']);

        $spreadsheet = new Spreadsheet;
        $row = fn (string $dealer, ?string $cnNumber) => [$dealer, 1000000, 'PROGRAM DSA JULI 2026', $cnNumber, 'DSA'];
        $spreadsheet->getActiveSheet()->fromArray([
            ['Dealer Name', 'Support Amount', 'Nama Program', 'No CN', 'DSA/NPS FL'],
            $row('TOKO A', '2608000043'),
            $row('TOKO B', null),
            $row('TOKO C', '2608000043'),
            $row('TOKO D', '2608000045'),
        ]);
        $path = tempnam(sys_get_temp_dir(), 'draft_cn').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $errors = $this->postJson('/api/drafts/import', ['file' => new UploadedFile($path, 'draft.xlsx', null, null, true)])
            ->assertOk()
            ->assertJsonPath('data.success', 1)
            ->assertJsonPath('data.failed', 3)
            ->json('data.errors');

        $this->assertSame([
            'No CN wajib diisi karena dipakai sebagai nomor invoice.',
            'No CN 2608000043 sudah dipakai baris 2 di file ini.',
            'No CN 2608000045 sudah dipakai draft TOKO LAMA (Jawa Tengah).',
        ], array_column($errors, 'error'));
        $this->assertSame('ready', Draft::where('dealer_name', 'TOKO A')->value('status'));
    }

    public function test_can_import_excel_with_numbering_row_on_top_and_headers_on_row_2(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        // Row 1: Numbers (like 1, 2, 3, 4, 5...) as shown in user screenshot
        $numberRow = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27];
        $sheet->fromArray([$numberRow], null, 'A1');

        // Row 2: Actual headers (like RSM, Dealer Code, Kode BT...)
        $headers = [
            'No.', 'Region', 'RSM', 'Dealer Code', 'Kode BT', 'Nama Cust by CSA',
            'Dealer Name', 'Realqty', 'NPWP', 'Nama NPWP', 'Jenis NPWP', 'Jenis Pph',
            'Support Amount', 'DPP', 'DPP Lain', 'PPN', 'PPh', 'Netpay',
            'KODE ITEM', 'NAMA ITEM', 'Alamat', 'Nama Program', 'Periode Program',
            'No CN', 'Tanggal Inv', 'REFNOTE', 'DSA/NPS FL',
        ];
        $sheet->fromArray([$headers], null, 'A2');

        // Row 3: Data row
        $row1 = [
            '1', 'JABO', 'RSM01', 'DLR-OFF-001', 'BT01', 'Customer Off',
            'PT Dealer Offset', 10, '01.234.567.8-901.000', 'PT Dealer Offset', 'BADAN', 'BADAN',
            10000000, 9009009, 8258258, 990991, 180180, 9819820,
            'ITM01', 'Barang Promo A', 'Jl. Merdeka No 1', 'Program Q1', 'Jan-Mar 2026',
            'CN001', '2026-03-01', 'Ref Note 1', 'DSA',
        ];
        $sheet->fromArray([$row1], null, 'A3');

        $tempPath = @tempnam(sys_get_temp_dir(), 'test_draft_offset_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $uploadedFile = new UploadedFile(
            $tempPath,
            'draft_offset.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->postJson('/api/drafts/import', [
            'file' => $uploadedFile,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'total_rows' => 1,
                'success' => 1,
                'failed' => 0,
            ],
        ]);

        $draft = Draft::where('dealer_code', 'DLR-OFF-001')->first();
        $this->assertNotNull($draft);
        $this->assertEquals('DSA', $draft->invoice_type);
        $this->assertEquals('ready', $draft->status);

        @unlink($tempPath);
    }

    public function test_can_import_excel_with_regular_invoice_type(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'NO', 'REGIONAL', 'RSM', 'DEALER CODE', 'KODE BT', 'CUSTOMER',
            'NAMA DEALER', 'TOP', 'NPWP', 'NAMA NPWP', 'TIPE BADAN USAHA', 'NPWP/NON NPWP',
            'SUPPORT CLAIM (INC)', 'DPP EXCL', 'DPP LAIN', 'PPN', 'PPH', 'NET PAY',
            'ITEM NO', 'ITEM DESCRIPTION', 'ALAMAT DEALER', 'NAMA PROGRAM', 'PERIODE PROGRAM',
            'No CN', 'Tanggal Inv', 'REFNOTE', 'DSA/NPS FL',
        ];
        $sheet->fromArray([$headers], null, 'A1');

        $row1 = [
            '1', 'JABO', 'RSM01', 'DLR-REG-01', 'BT01', 'Cust Regular',
            'Toko Regular', 0, '01.234.567.8-901.000', 'Toko Regular', 'BADAN', 'BADAN',
            10000000, 9009009, 8258258, 990991, 180180, 9819820,
            'ITM01', 'Barang Promo Regular', 'Jl. Sudirman 1', 'Program Regular', 'Jan-Mar 2026',
            'CN-REG-001', '2026-03-01', 'Ref Regular', 'REGULAR',
        ];
        $sheet->fromArray([$row1], null, 'A2');

        $tempPath = @tempnam(sys_get_temp_dir(), 'test_regular_').'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $uploadedFile = new UploadedFile(
            $tempPath,
            'draft_regular.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->postJson('/api/drafts/import', [
            'file' => $uploadedFile,
        ]);

        $response->assertStatus(200);
        $draft = Draft::where('dealer_code', 'DLR-REG-01')->first();
        $this->assertNotNull($draft);
        $this->assertEquals('REGULAR', $draft->invoice_type);
        $this->assertEquals('ready', $draft->status);

        @unlink($tempPath);
    }

    public function test_imported_draft_follows_the_area_of_its_store_in_master_dealer(): void
    {
        Dealer::create(['area' => 'jateng', 'kode_bt' => 'BT04850', 'id_realme' => 'DLBJ7072', 'dealer_name' => 'CV. BERKAH PANEN RAYA']);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['No.', 'Region', 'RSM', 'Dealer Code', 'Kode BT', 'Nama Cust by CSA', 'Dealer Name', 'Realqty', 'NPWP', 'Nama NPWP', 'Jenis NPWP', 'Jenis Pph', 'Support Amount', 'Dpp', 'Dpp Lain', 'Ppn', 'Pph', 'Netpay', 'KODE ITEM', 'NAMA ITEM', 'Alamat', 'Nama Program', 'Periode Program', 'No CN', 'Tanggal Inv', 'REFNOTE', 'DSA/NPS FL'],
            // Kode BT ditulis "BTO…" di Excel tetap dikenali sebagai BT04850 milik Jawa Tengah
            ['1', 'SOLO', 'LOGAN', 'DLBJ7072', 'BTO4850', 'CV. BERKAH PANEN RAYA', 'CV BERKAH PANEN RAYA', null, '0312494602526000', 'CV. BERKAH PANEN RAYA', 'BADAN', '2.0%', 17000000, 15315315, 14039039, 1684685, 306306, 16693694, '38000016', 'MARKETING SERVICE FEE', 'JL. MOH. YAMIN', 'PROGRAM PEAKSEASON JULI 2026', 'Periode 1-31 Juli 2026', '2608000044', '10/14/2026', 'REALME - DP SEMARANG', 'NPS FL'],
            // Toko yang tidak ada di Master Dealer tetap ikut tab tempat import
            ['2', 'BANDUNG', 'RSM', 'DLXX0001', 'BT99999', 'TOKO BARU', 'TOKO BARU', null, '-', 'TOKO BARU', 'PRIBADI', '2.5%', 1000000, 900901, 0, 0, 22523, 878378, '38000017', 'MARKETING SERVICE FEE', 'JL. BANDUNG', 'PROGRAM PEAKSEASON JULI 2026', 'Periode 1-31 Juli 2026', '2608000046', '10/14/2026', 'REALME - DP BANDUNG', 'NPS FL'],
        ]);
        $tempPath = @tempnam(sys_get_temp_dir(), 'test_area_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        $response = $this->postJson('/api/drafts/import', [
            'file' => new UploadedFile($tempPath, 'draft_area.xlsx', null, null, true),
        ], ['X-Area' => 'jabar']);

        $response->assertOk()->assertJsonPath('data.moved_to_area', ['Jawa Tengah' => 1]);
        $this->assertSame('jateng', Draft::withoutGlobalScopes()->where('kode_bt', 'BTO4850')->value('area'));
        $this->assertSame('jabar', Draft::withoutGlobalScopes()->where('kode_bt', 'BT99999')->value('area'));

        @unlink($tempPath);
    }

    public function test_can_manually_create_draft_invoice(): void
    {
        $payload = [
            'dealer_code' => 'TEST-DLR-MANUAL',
            'dealer_name' => 'Toko Manual Sejahtera',
            'customer_name' => 'PT TOP TELEMARKETING',
            'region' => 'BIG BANDUNG',
            'kode_bt' => 'BT889',
            'program_name' => 'PROGRAM DSA MARET 2026',
            'program_period' => 'Maret 2026',
            'cn_number' => 'CN-MANUAL-001',
            'invoice_type' => 'NPS FL',
            'support_amount' => 10000000,
            'pph_type' => 'BADAN',
            'npwp_type' => 'BADAN',
            'real_qty' => 1,
        ];

        $response = $this->postJson('/api/drafts', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'data' => [
                'dealer_name' => 'Toko Manual Sejahtera',
                'dealer_code' => 'TEST-DLR-MANUAL',
                'status' => 'ready',
                'invoice_type' => 'NPS FL',
            ],
        ]);

        $created = Draft::where('dealer_code', 'TEST-DLR-MANUAL')->first();
        $this->assertNotNull($created);
        $this->assertEquals(10000000, (float) $created->support_amount);
        $this->assertEquals(9009009, (float) $created->dpp);
        $this->assertEquals(990991, (float) $created->ppn);
        $this->assertEquals(180180, (float) $created->pph);
        $this->assertEquals(9819820, (float) $created->netpay);
        $this->assertTrue($created->validation_notes['is_matched']);
    }

    public function test_can_delete_draft_invoice(): void
    {
        $draft = Draft::create([
            'no' => '999',
            'dealer_name' => 'Toko Hapus Test',
            'program_name' => 'PROGRAM TEST',
            'support_amount' => 1000000,
            'status' => 'ready',
        ]);

        $response = $this->deleteJson("/api/drafts/{$draft->id}");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('drafts', ['id' => $draft->id]);
    }
}
