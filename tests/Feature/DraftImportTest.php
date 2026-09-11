<?php

namespace Tests\Feature;

use App\Models\Draft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DraftImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_import_draft_excel_with_success_and_row_errors(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 1. Headers matching required format
        $headers = [
            'No.', 'Region', 'RSM', 'Dealer Code', 'Kode BT', 'Nama Cust by CSA',
            'Dealer Name', 'Realqty', 'NPWP', 'Nama NPWP', 'Jenis NPWP', 'Jenis Pph',
            'Support Amount', 'Dpp', 'Dpp Lain', 'Ppn', 'Pph', 'Netpay',
            'KODE ITEM', 'NAMA ITEM', 'Alamat', 'Nama Program', 'Periode Program',
            'No CN', 'Tanggal Inv', 'REFNOTE', 'DSA/NPS FL'
        ];
        $sheet->fromArray([$headers], null, 'A1');

        // 2. Row 1: Valid DSA (BADAN)
        $row1 = [
            '1', 'JABO', 'RSM01', 'DLR001', 'BT01', 'Cust A',
            'PT Dealer A', 10, '01.234.567.8-901.000', 'PT Dealer A', 'BADAN', 'BADAN',
            10000000, 9009009, 8258258, 990991, 180180, 9819820,
            'ITM01', 'Barang Promo A', 'Jl. Merdeka No 1', 'Program Q1', 'Jan-Mar 2026',
            'CN001', '2026-03-01', 'Ref Note 1', 'DSA'
        ];

        // 3. Row 2: Valid NPS FL (PRIBADI)
        $row2 = [
            '2', 'JABO', 'RSM01', 'DLR002', 'BT02', 'Cust B',
            'Toko B', 5, '02.234.567.8-901.000', 'Toko B', 'PRIBADI', 'PRIBADI',
            5000000, 4504505, 0, 0, 112613, 4391892,
            'ITM02', 'Barang Promo B', 'Jl. Sudirman No 2', 'Program Q1', 'Jan-Mar 2026',
            'CN002', '2026-03-02', 'Ref Note 2', 'NPS FL'
        ];

        // 4. Row 3: Invalid DSA/NPS FL (should be error status, but not crash import)
        $row3 = [
            '3', 'JABO', 'RSM01', 'DLR003', 'BT03', 'Cust C',
            'Toko C', 2, '03.234.567.8-901.000', 'Toko C', 'BADAN', 'BADAN',
            2000000, 1801802, 1651652, 198198, 36036, 1963964,
            'ITM03', 'Barang Promo C', 'Jl. Thamrin No 3', 'Program Q1', 'Jan-Mar 2026',
            'CN003', '2026-03-03', 'Ref Note 3', 'UNKNOWN' // Invalid type!
        ];

        $sheet->fromArray([$row1, $row2, $row3], null, 'A2');

        // Save to temporary file
        $tempPath = tempnam(sys_get_temp_dir(), 'test_draft_') . '.xlsx';
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

    public function test_can_import_excel_with_numbering_row_on_top_and_headers_on_row_2(): void
    {
        $spreadsheet = new Spreadsheet();
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
            'No CN', 'Tanggal Inv', 'REFNOTE', 'DSA/NPS FL'
        ];
        $sheet->fromArray([$headers], null, 'A2');

        // Row 3: Data row
        $row1 = [
            '1', 'JABO', 'RSM01', 'DLR-OFF-001', 'BT01', 'Customer Off',
            'PT Dealer Offset', 10, '01.234.567.8-901.000', 'PT Dealer Offset', 'BADAN', 'BADAN',
            10000000, 9009009, 8258258, 990991, 180180, 9819820,
            'ITM01', 'Barang Promo A', 'Jl. Merdeka No 1', 'Program Q1', 'Jan-Mar 2026',
            'CN001', '2026-03-01', 'Ref Note 1', 'DSA'
        ];
        $sheet->fromArray([$row1], null, 'A3');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_draft_offset_') . '.xlsx';
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
}
