<?php

namespace Tests\Feature;

use App\Area;
use App\Models\DataProgram;
use App\Models\User;
use App\Services\DataProgramSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DataProgramPoImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_import_fills_no_po_sj_and_no_transaksi_by_kode_bt_and_program_name(): void
    {
        Http::preventStrayRequests();
        Http::fake(['script.google.com/*' => Http::response(['updated_count' => 1])]);
        Cache::forever(Area::Jateng->settingKey(DataProgramSyncService::CACHE_KEY_WEBAPP_URL), 'https://script.google.com/macros/s/JATENG_APP/exec');

        $peakSeason = $this->dataProgram('jateng', 'BTO5165', 'PROGRAM PEAKSEASON SEPTEMBER 2026', ['no_po_sj' => '2206000012']);
        $nonPromotor = $this->dataProgram('jateng', 'BT10157', 'PROGRAM SO NON PROMOTOR MEI 2026', ['no_transaksi' => '2606260790']);
        $otherProgram = $this->dataProgram('jateng', 'BT10157', 'PROGRAM NPS FL AGUSTUS 2026');
        $jabar = $this->dataProgram('jabar', 'BT04232', 'PROGRAM PEAK SEASON MARET 2026');

        $response = $this->postJson('/api/data-program/import-po', ['file' => $this->excel([
            ['Kode BT', 'NAMA PROGRAM', 'NO PO/SJ', 'No. Trs'],
            // "BT0…" in the file is the same store as "BTO…" in Data Program; a number replaces the old one
            ['BT05165', 'PROGRAM PEAKSEASON SEPTEMBER 2026', 2610000373, null],
            // An empty No. Trs keeps the number already there; spacing in the program name doesn't matter
            ['BT10157', 'program so  non-promotor mei 2026', 2606000424, null],
            ['BT04232', 'PROGRAM PEAK SEASON MARET 2026', 2604000363, 2604260001],
            ['BT99999', 'PROGRAM TIDAK ADA', 2600000001, null],
        ])], ['X-Area' => 'jateng']);

        $response->assertOk()
            ->assertJsonPath('data.total_rows', 4)
            ->assertJsonPath('data.updated', 3)
            ->assertJsonPath('data.replaced', 1)
            ->assertJsonPath('data.not_found', [['row' => 5, 'kode_bt' => 'BT99999', 'program' => 'PROGRAM TIDAK ADA']]);

        $this->assertSame(['2610000373', null], [$peakSeason->fresh()->no_po_sj, $peakSeason->fresh()->no_transaksi]);
        $this->assertSame(['2606000424', '2606260790'], [$nonPromotor->fresh()->no_po_sj, $nonPromotor->fresh()->no_transaksi]);
        $this->assertNull($otherProgram->fresh()->no_po_sj);
        // Rows of the other area match too
        $this->assertSame(['2604000363', '2604260001'], [$jabar->fresh()->no_po_sj, $jabar->fresh()->no_transaksi]);

        // The changed rows go to the spreadsheet of their own area
        $this->assertSame(['sent', 'sent'], $response->json('data.sheet_push.*.status'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'JATENG_APP')
            && collect($request['rows'])->pluck('no_po_sj')->sort()->values()->all() === ['2606000424', '2610000373']);
        Http::assertSent(fn (Request $request) => ! str_contains($request->url(), 'JATENG_APP')
            && collect($request['rows'])->pluck('no_transaksi')->all() === ['2604260001']);
    }

    public function test_import_into_an_area_without_a_spreadsheet_only_saves_the_numbers_in_the_app(): void
    {
        Http::preventStrayRequests();
        $dataProgram = $this->dataProgram('jateng', 'BTO5165', 'PROGRAM PEAKSEASON SEPTEMBER 2026');

        $this->postJson('/api/data-program/import-po', ['file' => $this->excel([
            ['Kode BT', 'NAMA PROGRAM', 'NO PO/SJ', 'No. Trs'],
            ['BTO5165', 'PROGRAM PEAKSEASON SEPTEMBER 2026', 2610000373, null],
        ])], ['X-Area' => 'jateng'])
            ->assertOk()
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.sheet_push', [[
                'area' => 'Jawa Tengah',
                'status' => 'not_connected',
                'message' => 'belum terhubung ke Google Spreadsheet, jadi nomor hanya tersimpan di aplikasi.',
            ]]);

        $this->assertSame('2610000373', $dataProgram->fresh()->no_po_sj);
        Http::assertNothingSent();
    }

    public function test_import_rejects_a_file_without_the_kode_bt_program_and_number_columns(): void
    {
        $this->postJson('/api/data-program/import-po', ['file' => $this->excel([
            ['Dealer Name', 'Support Amount'],
            ['TOKO A', 1000000],
        ])])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Kolom Kode BT, Nama Program, dan No PO/SJ atau No. Trs tidak ditemukan di file.');
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function dataProgram(string $area, string $kodeBt, string $programName, array $attributes = []): DataProgram
    {
        return DataProgram::create([
            'area' => $area,
            'kode_bt' => $kodeBt,
            'program_name' => $programName,
            'dealer_name' => "TOKO {$kodeBt}",
            'row_hash' => "row_{$area}_{$kodeBt}_".md5($programName),
            ...$attributes,
        ]);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function excel(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'po_import').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'Import No PO dan Transaksi.xlsx', null, null, true);
    }
}
