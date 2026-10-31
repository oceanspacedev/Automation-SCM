<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Dealer;
use App\Models\ProgramSubmission;
use App\Models\User;
use App\Services\DealerAgreementImportService;
use App\Services\DealerImportService;
use App\Services\DocumentAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use ZipArchive;

class DealerAgreementTest extends TestCase
{
    use RefreshDatabase;

    private const MASTER_AGREEMENT = 'https://drive.google.com/open?id=agreement_toko_jaya';

    protected function setUp(): void
    {
        parent::setUp();

        // Dokumen disimpan lokal (tanpa SeaweedFS / S3)
        config(['filesystems.disks.s3.key' => null]);
        Storage::fake('public');
    }

    private function dealer(array $attributes = []): Dealer
    {
        return Dealer::create($attributes + [
            'area' => 'jabar',
            'kode_bt' => 'BT07777',
            'id_realme' => 'DLAA0001',
            'dealer_name' => 'TOKO JAYA SELULAR',
            'region' => 'BIG BANDUNG',
            'is_active' => true,
        ]);
    }

    private function submission(string $idReal, string $agreementUrl, string $brand = 'realme'): ProgramSubmission
    {
        return ProgramSubmission::create([
            'area' => 'jabar',
            'brand' => $brand,
            'row_hash' => uniqid('sub_', true),
            'id_real' => $idReal,
            'dealer_name' => 'TOKO JAYA SELULAR',
            'program_name' => 'PROGRAM DSA JULI 2026',
            'agreement_url' => $agreementUrl,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submitForm(array $overrides = [], array $headers = []): TestResponse
    {
        return $this->postJson('/api/program-submissions/submit-form', $overrides + [
            'program_name' => 'PROGRAM DSA AGUSTUS 2026',
            'region' => 'BIG BANDUNG',
            'id_real' => 'DLAA0001',
            'kode_bt' => 'BT07777',
            'dealer_name' => 'TOKO JAYA SELULAR',
            'sales_name' => 'SANDY ARJAYAN',
            'credit_note_file' => UploadedFile::fake()->create('credit_note.pdf', 50, 'application/pdf'),
        ], $headers + ['X-Area' => 'jabar']);
    }

    public function test_fill_command_gives_each_store_its_latest_realme_agreement(): void
    {
        $withoutAgreement = $this->dealer();
        $withAgreement = $this->dealer(['kode_bt' => 'BT08888', 'id_realme' => 'DLBB0002', 'agreement_url' => 'https://drive.google.com/open?id=sudah_ada']);
        Brand::factory()->create(['slug' => 'xiaomi', 'name' => 'Xiaomi']);

        $this->submission('DLAA0001', 'https://drive.google.com/open?id=lama');
        $this->submission('BT07777', 'https://drive.google.com/open?id=terbaru');
        $this->submission('DLAA0001', 'https://drive.google.com/open?id=xiaomi', 'xiaomi');
        $this->submission('DLBB0002', 'https://drive.google.com/open?id=baru_toko_lain');
        $this->submission('DLZZ9999', 'https://drive.google.com/open?id=toko_tidak_dikenal');

        $this->artisan('dealers:fill-agreements')
            ->expectsOutputToContain('Agreement terisi untuk 1 toko. 1 pengajuan dilewati')
            ->assertSuccessful();

        $this->assertSame('https://drive.google.com/open?id=terbaru', $withoutAgreement->fresh()->agreement_url);
        $this->assertNotNull($withoutAgreement->fresh()->agreement_updated_at);
        $this->assertSame('https://drive.google.com/open?id=sudah_ada', $withAgreement->fresh()->agreement_url);
    }

    /**
     * A Data Program workbook whose RAW JABAR rows run from old to new programs.
     *
     * @param  list<array{0: string, 1: string}>  $rows  [kode BT, agreement link]
     */
    private function dataProgramFile(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('TEMPLATE JABAR');
        $spreadsheet->createSheet()->setTitle('RAW JABAR')->fromArray([
            ['VLOOKUP', 'NAMA DEALER', 'Kode BT', 'AGREMENT'],
            ...array_map(fn (array $row) => ['', 'TOKO', ...$row], $rows),
        ]);
        $path = tempnam(sys_get_temp_dir(), 'data-program').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /**
     * Fake Google Drive (each file's bytes carry its id, as a PDF unless given) and the AI router, which answers with
     * the inspection given for the document's id (an HTTP 500 when there is none).
     *
     * @param  array<string, array<string, mixed>>  $inspections  Drive file id => AI answer
     * @param  array<string, string>  $files  Drive file id => file bytes
     */
    private function fakeDriveAndAi(array $inspections, array $files = []): void
    {
        config([
            'concurrency.default' => 'sync',
            'services.openai_compatible.base_url' => 'https://ai.test/v1',
            'services.openai_compatible.api_key' => 'test-key',
            'services.openai_compatible.model' => 'test-model',
            'services.openai_compatible.fallback_model' => null,
        ]);

        Http::fake(function (Request $request) use ($inspections, $files) {
            if (str_contains($request->url(), 'drive.google.com')) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

                return str_starts_with($query['id'], 'tidak_dibagikan')
                    ? Http::response('Not Found', 404)
                    : Http::response($files[$query['id']] ?? "%PDF-1.4 {$query['id']}", 200, ['Content-Type' => 'application/pdf']);
            }

            $document = collect($request['messages'][1]['content'])->firstWhere('type', 'image_url')['image_url']['url'];
            $id = substr(base64_decode(explode(',', $document)[1]), strlen('%PDF-1.4 '));

            return isset($inspections[$id])
                ? Http::response(['choices' => [['message' => ['content' => json_encode($inspections[$id])]]]])
                : Http::response(['error' => 'unavailable'], 500);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function inspection(bool $isAgreement = true, bool $nameFilled = true, bool $hasSignature = true, ?string $stampName = null): array
    {
        return [
            'is_agreement' => $isAgreement, 'dealer_name' => $nameFilled ? 'TOKO' : null, 'name_filled' => $nameFilled,
            'has_signature' => $hasSignature, 'has_stamp' => $stampName !== null, 'stamp_name' => $stampName, 'note' => '',
        ];
    }

    public function test_an_agreement_without_the_store_name_needs_a_signature_and_the_stores_own_stamp(): void
    {
        $analysis = app(DocumentAnalysisService::class);

        $this->assertTrue($analysis->isAcceptableAgreement($this->inspection(hasSignature: false), 'CV MAJU CELL'));
        $this->assertTrue($analysis->isAcceptableAgreement($this->inspection(nameFilled: false, stampName: 'MAJU CELL'), 'CV MAJU CELL'));
        $this->assertFalse($analysis->isAcceptableAgreement($this->inspection(nameFilled: false), 'CV MAJU CELL'));
        $this->assertFalse($analysis->isAcceptableAgreement($this->inspection(nameFilled: false, stampName: 'SINAR PONSEL'), 'CV MAJU CELL'));
        $this->assertFalse($analysis->isAcceptableAgreement($this->inspection(nameFilled: false, stampName: 'CELL'), 'CV MAJU CELL'));
        $this->assertFalse($analysis->isAcceptableAgreement($this->inspection(nameFilled: false, hasSignature: false, stampName: 'MAJU CELL'), 'CV MAJU CELL'));
        $this->assertFalse($analysis->isAcceptableAgreement($this->inspection(isAgreement: false), 'CV MAJU CELL'));
    }

    public function test_an_agreement_checked_before_stamp_names_were_read_is_checked_again(): void
    {
        $this->fakeDriveAndAi(['ttd_cap_toko_a_0000000' => $this->inspection(nameFilled: false, stampName: 'TOKO JAYA SELULAR')]);
        Cache::forever('agreement_inspection:ttd_cap_toko_a_0000000', ['is_agreement' => true, 'name_filled' => false, 'has_signature' => true, 'has_stamp' => true, 'dealer_name' => null, 'note' => '']);

        $inspection = app(DocumentAnalysisService::class)->inspectAgreement('https://drive.google.com/open?id=ttd_cap_toko_a_0000000');

        $this->assertSame('TOKO JAYA SELULAR', $inspection['stamp_name']);
    }

    public function test_fill_command_takes_each_stores_newest_acceptable_agreement_from_a_data_program_file(): void
    {
        $link = fn (string $id) => "https://drive.google.com/open?id={$id}";
        $olderComplete = $this->dealer();
        $outdated = $this->dealer(['kode_bt' => 'BT05768', 'agreement_url' => $link('lama_toko_b_00000000')]);
        $blank = $this->dealer(['kode_bt' => 'BT08888', 'agreement_url' => 'https://drive.google.com/file/d/kosong_toko_c_0000000/view']);
        $fromForm = $this->dealer(['kode_bt' => 'BT09999', 'agreement_url' => $link('dari_form_toko_d_0000')]);
        $notShared = $this->dealer(['kode_bt' => 'BT06666']);
        $nameOnly = $this->dealer(['kode_bt' => 'BT05555']);
        $aiDown = $this->dealer(['kode_bt' => 'BT04444', 'agreement_url' => $link('ai_gagal_toko_h_00000')]);
        $signatureOnly = $this->dealer(['kode_bt' => 'BT03333']);

        // Semua toko di tes ini bernama TOKO JAYA SELULAR
        $this->fakeDriveAndAi([
            'lengkap_toko_a_000000' => $this->inspection(),
            'credit_note_toko_a_00' => $this->inspection(isAgreement: false),
            'ttd_cap_toko_b_000000' => $this->inspection(nameFilled: false, stampName: 'JAYA SELULAR'),
            'kosong_toko_c_0000000' => $this->inspection(nameFilled: false, hasSignature: false),
            'nama_saja_toko_f_0000' => $this->inspection(hasSignature: false),
            'ttd_saja_toko_i_00000' => $this->inspection(nameFilled: false),
            'ttd_cap_lain_toko_i_0' => $this->inspection(nameFilled: false, stampName: 'MAJU MUNDUR PONSEL'),
        ]);

        $path = $this->dataProgramFile([
            ['BT07777', $link('lengkap_toko_a_000000')],
            ['BTO5768', $link('lama_toko_b_00000000')],
            ['BT08888', $link('kosong_toko_c_0000000')],
            ['BT07777', $link('credit_note_toko_a_00')],
            ['BTO5768', $link('ttd_cap_toko_b_000000')],
            ['BT09999', $link('excel_toko_d_00000000')],
            ['BT06666', $link('tidak_dibagikan_toko_e')],
            ['BT05555', $link('nama_saja_toko_f_0000')],
            ['BT04444', $link('ai_gagal_toko_h_00000')],
            ['BT00001', $link('toko_tidak_ada_000000')],
            ['BT03333', $link('ttd_saja_toko_i_00000')],
            ['BT03333', $link('ttd_cap_lain_toko_i_0')],
        ]);

        $this->artisan('dealers:fill-agreements', ['file' => $path])
            ->expectsOutputToContain('2 toko diisi, 1 diganti, 0 sudah sama')
            ->expectsOutputToContain('1 agreement dihapus dan 2 toko tetap kosong')
            ->expectsOutputToContain('1 toko tetap memakai agreement-nya')
            ->expectsOutputToContain('1 kode BT tidak ada di Master Dealer')
            ->expectsOutputToContain('1 toko belum bisa dicek')
            ->assertSuccessful();

        // Link terbaru toko A adalah Credit Note, jadi agreement lengkap yang lebih lama yang dipakai
        $this->assertSame($link('lengkap_toko_a_000000'), $olderComplete->fresh()->agreement_url);
        $this->assertNotNull($olderComplete->fresh()->agreement_updated_at);
        $this->assertSame($link('ttd_cap_toko_b_000000'), $outdated->fresh()->agreement_url);
        // Hanya tanda tangan, atau cap toko lain: tidak dipakai
        $this->assertNull($signatureOnly->fresh()->agreement_url);
        $this->assertNull($blank->fresh()->agreement_url);
        $this->assertSame($link('dari_form_toko_d_0000'), $fromForm->fresh()->agreement_url);
        $this->assertNull($notShared->fresh()->agreement_url);
        $this->assertSame($link('nama_saja_toko_f_0000'), $nameOnly->fresh()->agreement_url);
        $this->assertSame($link('ai_gagal_toko_h_00000'), $aiDown->fresh()->agreement_url);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'excel_toko_d'));

        // Dokumen yang sudah dicek tidak dikirim lagi ke AI; yang gagal dicoba lagi
        $aiCalls = fn () => collect(Http::recorded())->filter(fn (array $pair) => str_contains($pair[0]->url(), 'ai.test'))->count();
        $before = $aiCalls();
        $this->artisan('dealers:fill-agreements', ['file' => $path])->expectsOutputToContain('0 toko diisi, 0 diganti, 3 sudah sama')->assertSuccessful();
        $this->assertSame($before + 1, $aiCalls());

        $this->artisan('dealers:fill-agreements', ['file' => $path, '--sheet' => 'RAW JATENG'])->assertFailed();
    }

    public function test_a_draft_program_file_uploaded_in_master_dealer_fills_agreements_in_the_background(): void
    {
        Storage::fake('local');
        $dealer = $this->dealer();
        $this->fakeDriveAndAi(['lengkap_toko_a_000000' => $this->inspection()]);
        $file = $this->dataProgramFile([['BT07777', 'https://drive.google.com/open?id=lengkap_toko_a_000000']]);

        // Import berjalan di antrean (sync saat tes): file dikenali sebagai Draft Program dari kolom Kode BT & AGREMENT
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post('/api/dealers/import', ['file' => new UploadedFile($file, 'DRAFT TEMPLATE PROGRAM JABAR.xlsx', null, null, true)], ['Accept' => 'application/json'])
            ->assertAccepted()
            ->assertJsonPath('status.state', 'done')
            ->assertJsonPath('status.type', 'agreements')
            ->assertJsonPath('status.sheet', 'RAW JABAR')
            ->assertJsonPath('status.progress', ['done' => 1, 'total' => 1, 'checked' => 1])
            ->assertJsonPath('status.result.filled', 1);

        $this->assertSame('https://drive.google.com/open?id=lengkap_toko_a_000000', $dealer->fresh()->agreement_url);
        $this->assertSame([], Storage::disk('local')->allFiles('dealer-import'));
    }

    public function test_agreement_import_progress_moves_after_every_batch_of_documents(): void
    {
        $this->dealer();
        $this->dealer(['kode_bt' => 'BT08888']);
        $this->dealer(['kode_bt' => 'BT09999']);
        $this->fakeDriveAndAi([
            'lengkap_toko_a_000000' => $this->inspection(),
            'credit_note_toko_b_00' => $this->inspection(isAgreement: false),
            'lengkap_toko_b_000000' => $this->inspection(),
            'lengkap_toko_c_000000' => $this->inspection(),
        ]);
        $link = fn (string $id) => "https://drive.google.com/open?id={$id}";
        $path = $this->dataProgramFile([
            ['BT07777', $link('lengkap_toko_a_000000')],
            ['BT08888', $link('lengkap_toko_b_000000')],
            ['BT08888', $link('credit_note_toko_b_00')],
            ['BT09999', $link('lengkap_toko_c_000000')],
        ]);

        // Satu dokumen per kelompok: [toko selesai, total toko, dokumen dianalisis] naik setiap dokumen
        $progress = [];
        app(DealerAgreementImportService::class)->import($path, 'RAW JABAR', 1, function (int $done, int $total, int $checked) use (&$progress) {
            $progress[] = [$done, $total, $checked];
        });

        $this->assertSame([[1, 3, 1], [1, 3, 2], [2, 3, 3], [3, 3, 4], [3, 3, 4]], $progress);
    }

    public function test_a_word_agreement_is_sent_to_the_ai_as_its_text_and_images(): void
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', '<w:document><w:p><w:t>NAMA Toko : TOKO JAYA SELULAR</w:t></w:p></w:document>');
        $zip->addFromString('word/media/image1.png', "\x89PNG".str_repeat('x', 2048));
        $zip->close();

        // Stub AI didaftarkan dulu: stub pertama yang cocok yang dipakai
        Http::fake(['ai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode($this->inspection())]]]])]);
        $this->fakeDriveAndAi([], ['agreement_word_toko_a_0' => file_get_contents($zipPath)]);
        $dealer = $this->dealer();

        $this->artisan('dealers:fill-agreements', ['file' => $this->dataProgramFile([['BT07777', 'https://drive.google.com/open?id=agreement_word_toko_a_0']])])
            ->expectsOutputToContain('1 toko diisi')
            ->assertSuccessful();

        $this->assertSame('https://drive.google.com/open?id=agreement_word_toko_a_0', $dealer->fresh()->agreement_url);
        Http::assertSent(function (Request $request) {
            $parts = collect($request['messages'][1]['content'] ?? []);

            return str_contains($request->url(), 'ai.test')
                && str_contains($parts->firstWhere('type', 'text')['text'], 'NAMA Toko : TOKO JAYA SELULAR')
                && str_starts_with($parts->firstWhere('type', 'image_url')['image_url']['url'], 'data:image/png;base64,');
        });
    }

    public function test_the_realme_form_uses_the_stores_master_agreement_without_an_upload(): void
    {
        $this->dealer(['agreement_url' => self::MASTER_AGREEMENT]);

        // Form publik hanya tahu toko sudah punya agreement, link-nya tidak dikirim
        $this->getJson('/api/dealers/suggestions?query=JAYA', ['X-Area' => 'jabar'])
            ->assertJsonPath('data.0.has_agreement', true)
            ->assertJsonMissingPath('data.0.agreement_url');
        $this->getJson('/api/dealers/lookup?id_realme=BT07777', ['X-Area' => 'jabar'])
            ->assertJsonPath('data.has_agreement', true)
            ->assertJsonMissingPath('data.agreement_url');

        $this->submitForm()->assertOk();

        $this->assertSame(self::MASTER_AGREEMENT, ProgramSubmission::where('program_name', 'PROGRAM DSA AGUSTUS 2026')->value('agreement_url'));
    }

    public function test_an_uploaded_agreement_replaces_the_master_and_stores_without_one_must_upload(): void
    {
        $dealer = $this->dealer(['agreement_url' => self::MASTER_AGREEMENT]);

        $this->submitForm(['agreement_file' => UploadedFile::fake()->create('agreement_baru.pdf', 50, 'application/pdf')])->assertOk();

        $newAgreement = $dealer->fresh()->agreement_url;
        $this->assertNotSame(self::MASTER_AGREEMENT, $newAgreement);
        $this->assertStringContainsString('/storage/program_documents/', $newAgreement);

        $this->dealer(['kode_bt' => 'BT08888', 'id_realme' => 'DLBB0002', 'dealer_name' => 'TOKO TANPA AGREEMENT']);
        $this->submitForm(['id_real' => 'DLBB0002', 'kode_bt' => 'BT08888', 'dealer_name' => 'TOKO TANPA AGREEMENT'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['agreement_file']);
    }

    public function test_admin_edits_the_agreement_and_a_full_dealer_import_keeps_it(): void
    {
        $dealer = $this->dealer();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->putJson("/api/dealers/{$dealer->id}", ['kode_bt' => 'BT07777', 'dealer_name' => 'TOKO JAYA SELULAR', 'agreement_url' => self::MASTER_AGREEMENT])
            ->assertOk()
            ->assertJsonPath('data.agreement_url', self::MASTER_AGREEMENT);
        $this->assertNotNull($dealer->fresh()->agreement_updated_at);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([['ID Customer', 'Nama Customer', 'Big Area'], ['BT07777', 'TOKO JAYA SELULAR', 'BIG BANDUNG']]);
        $path = tempnam(sys_get_temp_dir(), 'dealers').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        app(DealerImportService::class)->import($path, replaceExisting: true);

        $this->assertSame(self::MASTER_AGREEMENT, Dealer::withoutGlobalScopes()->where('kode_bt', 'BT07777')->value('agreement_url'));
    }

    public function test_the_dealer_list_filters_stores_with_or_without_an_agreement(): void
    {
        $this->dealer(['agreement_url' => self::MASTER_AGREEMENT]);
        $this->dealer(['kode_bt' => 'BT08888', 'dealer_name' => 'TOKO TANPA AGREEMENT']);
        $this->dealer(['kode_bt' => 'BT09999', 'dealer_name' => 'TOKO AGREEMENT KOSONG', 'agreement_url' => '']);

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $kodeBts = fn (string $agreement) => collect($this->getJson("/api/dealers?agreement={$agreement}", ['X-Area' => 'jabar'])->assertOk()->json('data.data'))
            ->pluck('kode_bt')->sort()->values()->all();

        $this->assertSame(['BT07777'], $kodeBts('yes'));
        $this->assertSame(['BT08888', 'BT09999'], $kodeBts('no'));
        $this->assertSame(['BT07777', 'BT08888', 'BT09999'], $kodeBts('all'));
    }
}
