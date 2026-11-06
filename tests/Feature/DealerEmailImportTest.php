<?php

namespace Tests\Feature;

use App\Models\Dealer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DealerEmailImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_import_fills_and_updates_store_emails_in_every_area(): void
    {
        $empty = Dealer::create(['area' => 'jabar', 'kode_bt' => 'BT05165', 'dealer_name' => 'NEWCO CELL']);
        $old = Dealer::create(['area' => 'jateng', 'kode_bt' => 'BT10157', 'dealer_name' => 'ROXY CELL', 'email' => 'lama@roxy.test']);
        $kept = Dealer::create(['area' => 'jabar', 'kode_bt' => 'BT04232', 'dealer_name' => 'SINAR CELL', 'email' => 'sinar@toko.test']);
        $byName = Dealer::create(['area' => 'jateng', 'kode_bt' => 'BT20001', 'dealer_name' => 'ABADI PONSEL']);

        $file = $this->excel([
            ['Kode BT', 'Nama Dealer', 'Email'],
            // "BTO…" in the file is the same store as "BT0…"; two addresses in one cell are both kept
            ['BTO5165', 'NEWCO CELL', 'Newco@Mail.test; admin@newco.test'],
            ['BT10157', 'ROXY CELL', 'baru@roxy.test'],
            // An empty cell keeps the email the store already has
            ['BT04232', 'SINAR CELL', ''],
            [null, 'abadi ponsel', 'abadi@ponsel.test'],
            ['BT99999', 'TOKO TIDAK ADA', 'x@tidakada.test'],
            ['BT05165', 'NEWCO CELL', 'bukan email'],
        ]);

        // Preview first: nothing is saved yet
        $this->post('/api/dealers/import-emails', ['file' => $file, 'preview' => 1], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.filled', 2)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.rows.0.new_email', 'newco@mail.test, admin@newco.test')
            ->assertJsonPath('data.rows.1.old_email', 'lama@roxy.test');
        $this->assertNull($empty->fresh()->email);

        $this->post('/api/dealers/import-emails', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.total_rows', 6)
            ->assertJsonPath('data.filled', 2)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.not_found', [['row' => 6, 'key' => 'BT99999 · TOKO TIDAK ADA', 'reason' => 'Kode BT tidak ada di Master Dealer']])
            ->assertJsonPath('data.invalid', [['row' => 7, 'key' => 'BT05165 · NEWCO CELL', 'email' => 'bukan email']]);

        $this->assertSame('newco@mail.test, admin@newco.test', $empty->fresh()->email);
        $this->assertSame('baru@roxy.test', $old->fresh()->email);
        $this->assertSame('sinar@toko.test', $kept->fresh()->email);
        $this->assertSame('abadi@ponsel.test', $byName->fresh()->email);
    }

    public function test_store_names_match_loosely_and_the_preview_picks_the_store_for_unclear_names(): void
    {
        $sehat = Dealer::create(['area' => 'jabar', 'kode_bt' => 'BT05872', 'dealer_name' => 'CV SEHAT BAHAGIA SUKSES', 'alias' => 'ERA 2000']);
        $garut = Dealer::create(['area' => 'jabar', 'kode_bt' => 'BT05516', 'dealer_name' => 'ADINDA CELL GARUT']);
        $bandung = Dealer::create(['area' => 'jabar', 'kode_bt' => 'BT02556', 'dealer_name' => 'ADINDA CELL BANDUNG']);
        $dhens = Dealer::create(['area' => 'jabar', 'kode_bt' => 'BT05928', 'dealer_name' => 'DHENS CELL', 'alias' => 'DENZ CELL']);
        $like = Dealer::create(['area' => 'jabar', 'kode_bt' => 'BT05901', 'dealer_name' => 'LIKE PHONE CELL']);
        $mitra = Dealer::create(['area' => 'jabar', 'kode_bt' => 'BT09587', 'dealer_name' => 'CV. MITRA MOBILE TEKNOLOGI']);

        $file = $this->excel([
            ['Nama Dealer', 'Email'],
            // The file's name contains the store's name (and its alias): offered, already picked
            ['ERA 2000 _ CV SEHAT BAHAGIA SUKSES', 'era@toko.test'],
            // Two stores contain this name: one has to be picked
            ['ADINDA CELL', 'adinda@toko.test'],
            // The alias matches exactly
            ['DENZ CELL', 'denz@toko.test'],
            // Only resembling: suggested, not picked
            ['LIKE PONSEL', 'like@toko.test'],
            ['CV. MITRA MOBILE TEKNOLOGI ( WE CELL, GANTI PKP)', 'mitra@toko.test'],
        ]);

        $preview = $this->post('/api/dealers/import-emails', ['file' => $file, 'preview' => 1], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.filled', 3)
            ->assertJsonPath('data.review.*.kind', ['similar', 'choose', 'suggest', 'similar'])
            ->assertJsonPath('data.review.0.selected', $sehat->id)
            ->assertJsonPath('data.review.1.selected', null)
            ->assertJsonPath('data.review.1.candidates.*.id', [$bandung->id, $garut->id])
            ->assertJsonPath('data.review.2.candidates.0.id', $like->id);
        $this->assertSame('Jawa Barat', $preview->json('data.review.1.candidates.0.area'));

        // Save with the stores picked in the preview: Adinda Garut, Like Phone, and Mitra skipped
        $this->post('/api/dealers/import-emails', [
            'file' => $file,
            'choices' => json_encode([3 => $garut->id, 5 => $like->id, 6 => 0]),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.filled', 4);

        $this->assertSame('era@toko.test', $sehat->fresh()->email);
        $this->assertSame('adinda@toko.test', $garut->fresh()->email);
        $this->assertNull($bandung->fresh()->email);
        $this->assertSame('denz@toko.test', $dhens->fresh()->email);
        $this->assertSame('like@toko.test', $like->fresh()->email);
        $this->assertNull($mitra->fresh()->email);
    }

    public function test_an_exported_master_dealer_file_can_be_imported_back_for_emails(): void
    {
        $dealer = Dealer::create(['area' => 'jabar', 'kode_bt' => 'BT05165', 'id_realme' => 'GS001', 'dealer_name' => 'NEWCO CELL']);

        $this->post('/api/dealers/import-emails', ['file' => $this->excel([
            ['ID CUSTOMER', 'NAMA CUSTOMER', 'ID GS', 'BIG AREA', 'Email', 'Agreement'],
            ['BT05165', 'NEWCO CELL', 'GS001', 'BIG CIREBON', 'newco@mail.test', null],
        ])], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.filled', 1);

        $this->assertSame('newco@mail.test', $dealer->fresh()->email);
    }

    public function test_a_row_with_a_kode_bt_or_id_gs_matches_by_that_code_whatever_its_name(): void
    {
        $shiba = Dealer::create(['area' => 'jateng', 'kode_bt' => 'BT00081', 'id_realme' => 'DLWY6135', 'dealer_name' => 'AM SHIBA CELL']);
        $indocell = Dealer::create(['area' => 'jateng', 'kode_bt' => 'BT05636', 'id_realme' => 'DLAF7716', 'dealer_name' => 'INDOCELL PURWOKERTO']);
        Dealer::create(['area' => 'jateng', 'kode_bt' => 'BT03561', 'id_realme' => 'DLJC6008', 'dealer_name' => 'INDOCELL KLAMPOK']);

        // Headers the import doesn't know by name: the codes are recognized from their values
        $this->post('/api/dealers/import-emails', ['file' => $this->excel([
            ['NO', 'KODE', 'ID', 'NAMA OUTLET', 'EMAIL TOKO'],
            [1, 'BT00081', 'DLWY6135', 'AM SHIBA SEMARANG', 'shiba@toko.test'],
            [2, 'bt 05636', null, 'INDO CELL PWT', 'indo@toko.test'],
            // A code that isn't in Master Dealer is not guessed from a resembling name
            [3, 'BT77777', 'DLZZ9999', 'INDOCELL NGADIREJO', 'ngadirejo@toko.test'],
        ])], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.columns.kode_bt.column', 'B')
            ->assertJsonPath('data.columns.id_realme.column', 'C')
            ->assertJsonPath('data.columns.email.header', 'EMAIL TOKO')
            ->assertJsonPath('data.filled', 2)
            ->assertJsonPath('data.review', [])
            ->assertJsonPath('data.not_found', [['row' => 4, 'key' => 'BT77777 · INDOCELL NGADIREJO', 'reason' => 'Kode BT tidak ada di Master Dealer']]);

        $this->assertSame('shiba@toko.test', $shiba->fresh()->email);
        $this->assertSame('indo@toko.test', $indocell->fresh()->email);
    }

    public function test_master_dealer_filters_stores_with_or_without_an_email(): void
    {
        Dealer::create(['area' => 'jabar', 'kode_bt' => 'BT05165', 'dealer_name' => 'NEWCO CELL', 'email' => 'newco@mail.test']);
        Dealer::create(['area' => 'jabar', 'kode_bt' => 'BT10157', 'dealer_name' => 'ROXY CELL']);
        Dealer::create(['area' => 'jabar', 'kode_bt' => 'BT04232', 'dealer_name' => 'SINAR CELL', 'email' => '']);

        $this->getJson('/api/dealers?email=yes', ['X-Area' => 'jabar'])
            ->assertOk()
            ->assertJsonPath('data.data.*.kode_bt', ['BT05165']);

        $names = collect($this->getJson('/api/dealers?email=no', ['X-Area' => 'jabar'])->json('data.data'))->pluck('kode_bt')->sort()->values()->all();
        $this->assertSame(['BT04232', 'BT10157'], $names);
    }

    public function test_a_file_without_an_email_column_is_refused(): void
    {
        $this->post('/api/dealers/import-emails', ['file' => $this->excel([
            ['Kode BT', 'Nama Dealer'],
            ['BT05165', 'NEWCO CELL'],
        ])], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Kolom Email dan Kode BT (atau ID GS / Nama Dealer) tidak ditemukan di file.');
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function excel(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'dealer_email').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'Email Dealer.xlsx', null, null, true);
    }
}
