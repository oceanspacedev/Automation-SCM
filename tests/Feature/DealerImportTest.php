<?php

namespace Tests\Feature;

use App\Jobs\ImportDealerFile;
use App\Models\DataProgram;
use App\Models\Dealer;
use App\Models\Draft;
use App\Models\MasterProgram;
use App\Models\Region;
use App\Models\User;
use App\Services\DataProgramSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DealerImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // API selain login, form publik & webhook butuh login
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        // File yang diunggah disimpan sementara di disk local sampai import di background selesai
        Storage::fake('local');
    }

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

        // Import berjalan di antrean (sync saat tes), jadi statusnya sudah selesai begitu respons kembali
        $this->post('/api/dealers/import', ['file' => new UploadedFile($file, 'Master Customer CV TOP.xlsx', null, null, true)], ['Accept' => 'application/json'])
            ->assertAccepted()
            ->assertJsonPath('status.state', 'done')
            ->assertJsonPath('status.type', 'dealers')
            ->assertJsonPath('status.file_name', 'Master Customer CV TOP.xlsx');

        $this->getJson('/api/dealers/import/status')
            ->assertOk()
            ->assertJsonPath('status.result.imported', 2)
            ->assertJsonPath('status.result.per_area', ['Jawa Barat' => 1, 'Jawa Tengah' => 1])
            ->assertJsonPath('status.result.skipped.0.kode_bt', 'BT05000')
            ->assertJsonPath('status.result.skipped.0.reason', 'Big Area & Reffnote kosong / tidak dikenal');
        $this->assertSame([], Storage::disk('local')->allFiles('dealer-import'));

        $this->assertDatabaseHas('dealers', ['kode_bt' => 'BT99999']);
        $this->assertDatabaseHas('dealers', ['kode_bt' => 'BT03000', 'area' => 'jateng']);

        $this->get('/api/dealers/import/skipped')->assertOk()->assertDownload('dealer-belum-diimport.xlsx');
    }

    public function test_exported_dealers_of_an_area_can_be_imported_again_unchanged(): void
    {
        $agreement = 'https://drive.google.com/open?id=1PhOk1VkVCQiFAmjqM9OmApTdbgF3SCmH';
        Dealer::create([
            'area' => 'jabar', 'kode_bt' => 'BT02657', 'dealer_name' => 'AWEI CELL', 'id_realme' => 'DLAA0001', 'npwp' => '3277030808650024',
            'npwp_type' => 'PRIBADI', 'telemarketing' => 'AKHMAD ZULFIKAR', 'telemarketing_phone' => '087778888837', 'region' => 'BIG BANDUNG', 'is_active' => false,
            'agreement_url' => $agreement,
        ]);
        Dealer::create(['area' => 'jateng', 'kode_bt' => 'BT03000', 'dealer_name' => 'TOKO SOLO', 'region' => 'BIG SOLO']);

        $rows = $this->downloadedSpreadsheetRows($this->get('/api/dealers/export?area=jabar')->assertOk());

        $this->assertSame(self::HEADER[0], $rows[0][0]);
        $this->assertCount(2, $rows);
        $this->assertSame(['BT02657', 'AWEI CELL', 'DLAA0001', 'Disabled', '3277030808650024', '087778888837', 'BIG BANDUNG'], [$rows[1][0], $rows[1][1], $rows[1][4], $rows[1][6], $rows[1][7], $rows[1][12], $rows[1][13]]);

        // Agreement di kolom terakhir: teks pendek "Link AGR" yang membuka dokumennya
        $file = $this->saveDownloadedSpreadsheet($this->get('/api/dealers/export?area=jabar'));
        $agreementCell = IOFactory::load($file)->getActiveSheet()->getCell('P2');
        $this->assertSame('Agreement', $rows[0][15]);
        $this->assertSame('Link AGR', $agreementCell->getValue());
        $this->assertSame($agreement, $agreementCell->getHyperlink()->getUrl());

        // File export langsung bisa diimport ulang tanpa mengubah data (agreement toko tetap)
        $this->post('/api/dealers/import', ['file' => new UploadedFile($file, 'export.xlsx', null, null, true)], ['Accept' => 'application/json'])
            ->assertAccepted()
            ->assertJsonPath('status.result.imported', 1)
            ->assertJsonPath('status.result.skipped', []);

        $this->assertDatabaseHas('dealers', ['kode_bt' => 'BT02657', 'area' => 'jabar', 'npwp' => '3277030808650024', 'telemarketing_phone' => '087778888837', 'is_active' => false, 'agreement_url' => $agreement]);
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

        // File lain baru ketahuan saat diproses di background: statusnya gagal dengan alasannya
        $this->post('/api/dealers/import', ['file' => new UploadedFile($path, 'lain.xlsx', null, null, true)], ['Accept' => 'application/json'])
            ->assertAccepted()
            ->assertJsonPath('status.state', 'failed')
            ->assertJsonPath('status.message', 'Kolom "ID CUSTOMER" tidak ditemukan di baris header file.');
        $this->assertSame([], Storage::disk('local')->allFiles('dealer-import'));
    }

    public function test_only_one_dealer_import_runs_at_a_time(): void
    {
        Queue::fake();
        $file = fn () => new UploadedFile($this->makeMasterFile([$this->row('BT02657', 'AWEI CELL', 'BIG BANDUNG')]), 'Master Customer CV TOP.xlsx', null, null, true);

        $this->post('/api/dealers/import', ['file' => $file()], ['Accept' => 'application/json'])
            ->assertAccepted()
            ->assertJsonPath('status.state', 'queued');
        Queue::assertPushed(ImportDealerFile::class);

        $this->post('/api/dealers/import', ['file' => $file()], ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonPath('status.state', 'queued');
        Queue::assertPushed(ImportDealerFile::class, 1);

        // Import yang statusnya tidak berubah lebih dari sejam dianggap berhenti, jadi import baru boleh mulai
        $this->travel(71)->minutes();
        $this->post('/api/dealers/import', ['file' => $file()], ['Accept' => 'application/json'])->assertAccepted();
        Queue::assertPushed(ImportDealerFile::class, 2);
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

    public function test_dealer_suggestions_report_the_total_and_load_the_next_page_with_an_offset(): void
    {
        foreach (['TOKO C', 'TOKO A', 'TOKO B'] as $index => $name) {
            Dealer::create(['area' => 'jabar', 'kode_bt' => 'BT0100'.$index, 'dealer_name' => $name, 'sales_person' => 'DODI PERMANA', 'is_active' => true]);
        }
        Dealer::create(['area' => 'jabar', 'kode_bt' => 'BT01009', 'dealer_name' => 'TOKO TUTUP', 'is_active' => false]);

        // Jumlah toko yang ditampilkan form adalah semua toko aktif, bukan hanya halaman pertama
        $this->getJson('/api/dealers/suggestions?limit=2', ['X-Area' => 'jabar'])
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.dealer_name', 'TOKO A')
            ->assertJsonPath('data.0.sales_person', 'DODI PERMANA');

        $this->getJson('/api/dealers/suggestions?limit=2&offset=2', ['X-Area' => 'jabar'])
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.dealer_name', 'TOKO C');
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
