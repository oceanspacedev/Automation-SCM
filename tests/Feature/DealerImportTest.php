<?php

namespace Tests\Feature;

use App\Models\DataProgram;
use App\Models\Dealer;
use App\Models\Draft;
use App\Models\MasterProgram;
use App\Models\Region;
use App\Services\DataProgramSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DealerImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = ['ID Customer', 'Nama Customer', 'Alias', 'Alamat', 'ID GS', 'Nama GS', 'Status GS', 'NPWP/NIK', 'NAMA NPWP/NIK', 'Reffnote', 'Status DL', 'Nama Customer', 'Jenis NPWP', 'Telemarketing', 'No. WA Telemarketing', 'Big Area', 'SALES AREA'];

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function makeMasterFile(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('Master Dealer CV TOP')->fromArray([self::HEADER, ...$rows]);
        $path = tempnam(sys_get_temp_dir(), 'dealers').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function row(string $kodeBt, string $name, string $bigArea, string $reffnote = 'REALME - DP BANDUNG', string $statusGs = 'Enabled', string $idGs = 'DLAA0001'): array
    {
        return [$kodeBt, $name, 'ALIAS '.$name, 'JL. CONTOH', $idGs, 'GS '.$name, $statusGs, '3277030808650024', 'PEMILIK '.$name, $reffnote, '-', $name, 'PRIBADI', 'AKHMAD ZULFIKAR', '087778888837', $bigArea, 'AMIN BURHANUDIN'];
    }

    public function test_import_replaces_master_dealer_and_maps_areas_from_big_area_or_reffnote(): void
    {
        Dealer::create(['area' => 'jabar', 'id_realme' => 'DUMMY01', 'kode_bt' => 'BT99999', 'dealer_name' => 'DEALER DUMMY']);

        $file = $this->makeMasterFile([
            $this->row('BT02657', 'AWEI CELL', 'BIG BANDUNG'),
            $this->row('BTO5768', 'TOKO JOGJA', 'BIG JOGJA', 'REALME - DP SEMARANG', 'Disabled', 'DLCB2930'),
            $this->row('BT03000', 'TOKO SOLO', 'BIG SOLO', 'REALME - DP SEMARANG', 'Enabled', 'DLCB2930'),
            $this->row('BT04000', 'TOKO TANPA BIG AREA', '-', 'REALME - DP PURWOKERTO'),
            $this->row('BT05000', 'TOKO TANPA AREA', '-', '-'),
            $this->row('BT02657', 'AWEI CELL BARU', 'BIG BANDUNG'),
            // "BTO5760" & "BT05760" adalah dua toko berbeda: keduanya harus tetap ada
            $this->row('BTO5760', 'BARITO CELL', 'BIG TEGAL', 'REALME - DP PURWOKERTO'),
            $this->row('BT05760', 'CV TRANS JAYA ELEKTRONIK', 'BIG CPS', 'REALME - DP BANDUNG', 'Disabled'),
        ]);

        $this->artisan('dealers:import', ['file' => $file])->assertSuccessful();

        $this->assertDatabaseMissing('dealers', ['kode_bt' => 'BT99999']);
        $this->assertSame(6, Dealer::withoutGlobalScopes()->count());

        $this->assertDatabaseHas('dealers', ['kode_bt' => 'BTO5760', 'dealer_name' => 'BARITO CELL', 'area' => 'jateng']);
        $this->assertDatabaseHas('dealers', ['kode_bt' => 'BT05760', 'dealer_name' => 'CV TRANS JAYA ELEKTRONIK', 'area' => 'jabar']);

        // Baris terakhir untuk kode BT dobel yang dipakai
        $this->assertDatabaseHas('dealers', ['kode_bt' => 'BT02657', 'dealer_name' => 'AWEI CELL BARU', 'area' => 'jabar', 'region' => 'BIG BANDUNG', 'telemarketing' => 'AKHMAD ZULFIKAR', 'telemarketing_phone' => '087778888837', 'sales_person' => 'AMIN BURHANUDIN', 'npwp_type' => 'PRIBADI']);

        // BTO… disimpan sebagai BT0…, JOGJA masuk Jawa Tengah, Disabled = nonaktif, ID GS boleh dipakai beberapa toko
        $this->assertDatabaseHas('dealers', ['kode_bt' => 'BT05768', 'area' => 'jateng', 'region' => 'BIG JOGJA', 'is_active' => false, 'id_realme' => 'DLCB2930']);
        $this->assertDatabaseHas('dealers', ['kode_bt' => 'BT03000', 'id_realme' => 'DLCB2930']);

        // Big Area kosong: area dari Reffnote, region dibiarkan kosong
        $this->assertDatabaseHas('dealers', ['kode_bt' => 'BT04000', 'area' => 'jateng', 'region' => null]);

        // Tanpa Big Area & Reffnote: dilewati dan dicatat di file
        $this->assertDatabaseMissing('dealers', ['kode_bt' => 'BT05000']);
        $this->assertFileExists(storage_path('app/dealer-import/dealer-belum-diimport.xlsx'));

        // Region dari Big Area otomatis masuk Master Region area masing-masing
        $this->assertTrue(Region::withoutGlobalScopes()->where(['area' => 'jateng', 'name' => 'BIG JOGJA'])->exists());
        $this->assertTrue(Region::withoutGlobalScopes()->where(['area' => 'jateng', 'name' => 'BIG SOLO'])->exists());
    }

    public function test_master_dealer_can_be_imported_from_the_web_without_removing_other_dealers(): void
    {
        Dealer::create(['area' => 'jabar', 'kode_bt' => 'BT99999', 'dealer_name' => 'DEALER LAMA']);

        $file = $this->makeMasterFile([
            $this->row('BT02657', 'AWEI CELL', 'BIG BANDUNG'),
            $this->row('BT03000', 'TOKO SOLO', 'BIG SOLO', 'REALME - DP SEMARANG'),
            $this->row('BT05000', 'TOKO TANPA AREA', '-', '-'),
        ]);

        $this->post('/api/dealers/import', ['file' => new UploadedFile($file, 'Master Customer CV TOP.xlsx', null, null, true)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.per_area', ['Jawa Barat' => 1, 'Jawa Tengah' => 1])
            ->assertJsonPath('data.skipped.0.kode_bt', 'BT05000')
            ->assertJsonPath('data.skipped.0.reason', 'Big Area & Reffnote kosong / tidak dikenal');

        $this->assertDatabaseHas('dealers', ['kode_bt' => 'BT99999']);
        $this->assertDatabaseHas('dealers', ['kode_bt' => 'BT03000', 'area' => 'jateng']);

        $this->get('/api/dealers/import/skipped')->assertOk()->assertDownload('dealer-belum-diimport.xlsx');
    }

    public function test_exported_dealers_of_an_area_can_be_imported_again_unchanged(): void
    {
        Dealer::create([
            'area' => 'jabar', 'kode_bt' => 'BT02657', 'dealer_name' => 'AWEI CELL', 'id_realme' => 'DLAA0001', 'npwp' => '3277030808650024',
            'npwp_type' => 'PRIBADI', 'telemarketing' => 'AKHMAD ZULFIKAR', 'telemarketing_phone' => '087778888837', 'region' => 'BIG BANDUNG', 'is_active' => false,
        ]);
        Dealer::create(['area' => 'jateng', 'kode_bt' => 'BT03000', 'dealer_name' => 'TOKO SOLO', 'region' => 'BIG SOLO']);

        $rows = $this->downloadedSpreadsheetRows($this->get('/api/dealers/export?area=jabar')->assertOk());

        $this->assertSame(self::HEADER[0], $rows[0][0]);
        $this->assertCount(2, $rows);
        $this->assertSame(['BT02657', 'AWEI CELL', 'DLAA0001', 'Disabled', '3277030808650024', '087778888837', 'BIG BANDUNG'], [$rows[1][0], $rows[1][1], $rows[1][4], $rows[1][6], $rows[1][7], $rows[1][12], $rows[1][13]]);

        // File export langsung bisa diimport ulang tanpa mengubah data
        $file = $this->saveDownloadedSpreadsheet($this->get('/api/dealers/export?area=jabar'));
        $this->post('/api/dealers/import', ['file' => new UploadedFile($file, 'export.xlsx', null, null, true)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.skipped', []);

        $this->assertDatabaseHas('dealers', ['kode_bt' => 'BT02657', 'area' => 'jabar', 'npwp' => '3277030808650024', 'telemarketing_phone' => '087778888837', 'is_active' => false]);
        $this->assertDatabaseHas('dealers', ['kode_bt' => 'BT03000', 'area' => 'jateng']);
    }

    public function test_dealer_import_rejects_files_that_are_not_the_master_dealer_excel(): void
    {
        $this->post('/api/dealers/import', ['file' => UploadedFile::fake()->create('dealer.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([['Kolom A', 'Kolom B'], ['x', 'y']]);
        $path = tempnam(sys_get_temp_dir(), 'dealers').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $this->post('/api/dealers/import', ['file' => new UploadedFile($path, 'lain.xlsx', null, null, true)], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'File tidak bisa diimport: Kolom "ID CUSTOMER" tidak ditemukan di baris header file.');
    }

    public function test_dealer_kode_bt_is_required_unique_and_normalized(): void
    {
        $this->postJson('/api/dealers', ['dealer_name' => 'TOKO A'], ['X-Area' => 'jabar'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kode_bt']);

        $this->postJson('/api/dealers', ['kode_bt' => 'bto1234', 'dealer_name' => 'TOKO A'], ['X-Area' => 'jabar'])
            ->assertCreated()
            ->assertJsonPath('data.kode_bt', 'BT01234');

        $this->postJson('/api/dealers', ['kode_bt' => 'BT01234', 'dealer_name' => 'TOKO B'], ['X-Area' => 'jabar'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kode_bt']);
    }

    public function test_dealer_suggestions_include_stores_without_id_realme(): void
    {
        Dealer::create(['area' => 'jabar', 'kode_bt' => 'BT07777', 'dealer_name' => 'TOKO TANPA GS', 'is_active' => true]);

        $this->getJson('/api/dealers/suggestions?query=BTO7777', ['X-Area' => 'jabar'])
            ->assertOk()
            ->assertJsonPath('data.0.dealer_name', 'TOKO TANPA GS')
            ->assertJsonPath('data.0.id_realme', 'BT07777');

        // Toko yang kode BT-nya tetap ditulis "BTO…" (karena "BT0…" dipakai toko lain) tetap bisa dicari & dibuka
        Dealer::create(['area' => 'jateng', 'kode_bt' => 'BTO5760', 'id_realme' => 'DLCD6294', 'dealer_name' => 'BARITO CELL', 'is_active' => true]);

        $this->getJson('/api/dealers/suggestions?query=BTO5760', ['X-Area' => 'jateng'])
            ->assertOk()
            ->assertJsonPath('data.0.dealer_name', 'BARITO CELL');
        $this->getJson('/api/dealers/suggestions?query=DLCD6294', ['X-Area' => 'jateng'])
            ->assertJsonPath('data.0.kode_bt', 'BTO5760');
        $this->getJson('/api/dealers/lookup?id_realme=BTO5760')
            ->assertJsonPath('data.dealer_name', 'BARITO CELL');
    }

    public function test_data_program_takes_sales_and_telemarketing_from_master_dealer(): void
    {
        Http::fake();

        MasterProgram::create(['code' => 'PROGRAM 900', 'name' => 'PROGRAM DEALER TM TEST']);
        Dealer::create(['area' => 'jabar', 'kode_bt' => 'BT02657', 'dealer_name' => 'AWEI CELL', 'telemarketing' => 'AKHMAD ZULFIKAR', 'telemarketing_phone' => '087778888837', 'sales_person' => null]);

        // Kode BT di draft ditulis "BTO…" tetap ketemu dealernya
        $draft = Draft::create(['area' => 'jabar', 'dealer_name' => 'AWEI CELL', 'kode_bt' => 'BTO2657', 'program_name' => 'PROGRAM DEALER TM TEST', 'support_amount' => 1000, 'status' => 'invoiced']);

        app(DataProgramSyncService::class)->syncDraftToDataProgram($draft, false);

        $dataProgram = DataProgram::withoutGlobalScopes()->where('draft_id', $draft->id)->first();
        $this->assertSame('AKHMAD ZULFIKAR', $dataProgram->telemarketing);
        $this->assertSame('087778888837', $dataProgram->telemarketing_phone);
        // Sales kosong di dealer dibiarkan kosong (Master Program tidak lagi menyimpan sales)
        $this->assertNull($dataProgram->sales_person);
    }
}
