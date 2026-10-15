<?php

namespace Tests\Feature;

use App\Area;
use App\Models\DataProgram;
use App\Models\Dealer;
use App\Models\Draft;
use App\Models\MasterProgram;
use App\Models\ProgramReconciliationLog;
use App\Models\ProgramSubmission;
use App\Models\Region;
use App\Services\AreaContext;
use App\Services\DataProgramSyncService;
use App\Services\ProgramReconciliationService;
use App\Services\ProgramSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AreaSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_program_list_only_shows_rows_of_selected_area(): void
    {
        DataProgram::create(['area' => 'jabar', 'row_hash' => 'row_2', 'dealer_name' => 'DEALER BANDUNG']);
        DataProgram::create(['area' => 'jateng', 'row_hash' => 'row_2', 'dealer_name' => 'DEALER SEMARANG']);

        $jateng = $this->getJson('/api/data-program', ['X-Area' => 'jateng'])->assertOk();
        $this->assertSame(['DEALER SEMARANG'], collect($jateng->json('items'))->pluck('dealer_name')->all());

        $jabar = $this->getJson('/api/data-program', ['X-Area' => 'jabar'])->assertOk();
        $this->assertSame(['DEALER BANDUNG'], collect($jabar->json('items'))->pluck('dealer_name')->all());
    }

    public function test_full_sync_webhook_of_one_area_does_not_touch_other_area_rows(): void
    {
        Http::fake();

        DataProgram::create(['area' => 'jabar', 'row_hash' => 'row_2', 'dealer_name' => 'DEALER JABAR BARIS 2']);
        DataProgram::create(['area' => 'jabar', 'row_hash' => 'row_9', 'dealer_name' => 'DEALER JABAR BARIS 9']);
        DataProgram::create(['area' => 'jabar', 'row_hash' => 'draft_1', 'dealer_name' => 'DEALER JABAR DRAFT']);

        $this->postJson('/api/webhooks/data-program?area=jateng', [
            'start_row' => 2,
            'is_full_sync' => true,
            'rows' => [
                ['DEALER SOLO', 'PROGRAM 1', 'BT001', 'PROGRAM DSA JATENG', '2026', 'BIG SOLO'],
            ],
        ])->assertOk()->assertJsonPath('saved_count', 1);

        $this->assertDatabaseHas('data_programs', ['area' => 'jateng', 'row_hash' => 'row_2', 'dealer_name' => 'DEALER SOLO']);
        $this->assertDatabaseHas('data_programs', ['area' => 'jabar', 'row_hash' => 'row_2', 'dealer_name' => 'DEALER JABAR BARIS 2']);
        $this->assertDatabaseHas('data_programs', ['area' => 'jabar', 'row_hash' => 'row_9']);
        $this->assertDatabaseHas('data_programs', ['area' => 'jabar', 'row_hash' => 'draft_1']);
    }

    public function test_form_program_sheet_sync_of_one_area_keeps_other_area_submissions(): void
    {
        $jabarSubmission = ProgramSubmission::create([
            'area' => 'jabar',
            'row_hash' => sha1('jabar-row'),
            'dealer_name' => 'TOKO BANDUNG',
            'program_name' => 'PROGRAM DSA JULI 2026',
        ]);

        $rows = [
            ['Timestamp', 'Region', 'ID Real', 'Nama Dealer', 'Nama Program', 'Nama Sales'],
            ['01/08/2026 10:00:00', 'BIG SEMARANG', 'IDME001', 'TOKO SEMARANG', 'PROGRAM DSA AGUSTUS 2026', 'ANDI'],
        ];

        app(AreaContext::class)->run(Area::Jateng, fn () => app(ProgramSubmissionService::class)->processSheetRows($rows));

        $this->assertNotNull($jabarSubmission->fresh());
        $this->assertDatabaseHas('program_submissions', ['area' => 'jateng', 'dealer_name' => 'TOKO SEMARANG']);
    }

    public function test_form_program_webhook_saves_submission_to_requested_area(): void
    {
        Http::fake();

        $this->postJson('/api/webhooks/form-program?area=jateng', [
            'namedValues' => [
                'Timestamp' => ['01/08/2026 10:00:00'],
                'REGION' => ['BIG SOLO'],
                'ID REAL' => ['IDME999'],
                'NAMA DEALER' => ['TOKO SOLO'],
                'NAMA PROGRAM' => ['PROGRAM DSA AGUSTUS 2026'],
                'NAMA SALES' => ['BUDI'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('program_submissions', ['area' => 'jateng', 'dealer_name' => 'TOKO SOLO']);
    }

    public function test_removed_area_is_not_accepted_and_falls_back_to_jawa_barat(): void
    {
        $this->assertNull(Area::fromInput('jatim'));

        // Webhook dengan area yang tidak dikenal tetap masuk ke Jawa Barat, bukan area baru
        Http::fake();
        $this->postJson('/api/webhooks/form-program?area=jatim', [
            'namedValues' => [
                'Timestamp' => ['01/08/2026 11:00:00'],
                'ID REAL' => ['IDME998'],
                'NAMA DEALER' => ['TOKO LAIN'],
                'NAMA PROGRAM' => ['PROGRAM DSA AGUSTUS 2026'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('program_submissions', ['area' => 'jabar', 'dealer_name' => 'TOKO LAIN']);
    }

    public function test_public_form_options_use_area_regions_and_the_shared_program_list(): void
    {
        // Area tanpa Master Region tidak memakai region Jawa Barat
        $this->getJson('/api/program-submissions/form-options', ['X-Area' => 'jateng'])
            ->assertOk()
            ->assertJsonPath('regions', []);

        Region::factory()->forArea(Area::Jateng)->create(['name' => 'BIG SOLO']);
        Region::factory()->forArea(Area::Jateng)->create(['name' => 'BIG SEMARANG']);
        Region::factory()->forArea(Area::Jateng)->inactive()->create(['name' => 'BIG LAMA']);

        $jateng = $this->getJson('/api/program-submissions/form-options', ['X-Area' => 'jateng'])
            ->assertOk()
            ->assertJsonPath('regions', ['BIG SEMARANG', 'BIG SOLO'])
            ->assertJsonPath('area.label', 'Jawa Tengah');

        // Form lama tanpa area (/form-realme) memakai region Jawa Barat, dengan daftar program yang sama
        $jabar = $this->getJson('/api/program-submissions/form-options')
            ->assertOk()
            ->assertJsonPath('regions', ['BIG BANDUNG', 'BIG CIREBON', 'BIG KARAWANG', 'BIG TASIK']);

        $this->assertNotEmpty($jabar->json('programs'));
        $this->assertSame($jabar->json('programs'), $jateng->json('programs'));
    }

    public function test_master_region_is_managed_per_area(): void
    {
        $this->postJson('/api/regions', ['name' => 'big solo'], ['X-Area' => 'jateng'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'BIG SOLO')
            ->assertJsonPath('data.area', 'jateng');

        // Nama sama boleh di area lain, tapi tidak boleh dobel di area yang sama
        $this->postJson('/api/regions', ['name' => 'BIG SOLO'], ['X-Area' => 'jateng'])->assertStatus(422);
        $this->postJson('/api/regions', ['name' => 'BIG SOLO'], ['X-Area' => 'jabar'])->assertCreated();

        $this->assertSame(['BIG SOLO'], $this->getJson('/api/regions/options', ['X-Area' => 'jateng'])->json('data'));
        $this->assertSame(
            ['BIG BANDUNG', 'BIG CIREBON', 'BIG KARAWANG', 'BIG SOLO', 'BIG TASIK'],
            $this->getJson('/api/regions/options', ['X-Area' => 'jabar'])->json('data')
        );
    }

    public function test_master_program_is_one_list_shared_by_all_areas(): void
    {
        MasterProgram::updateOrCreate(['name' => 'PROGRAM DSA AGUSTUS 2026'], ['code' => 'PROGRAM 343']);

        // Program yang ditambah dari mana pun memakai penomoran yang sama
        $this->postJson('/api/programs', ['name' => 'PROGRAM DSA SEPTEMBER 2026'], ['X-Area' => 'jateng'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'PROGRAM 344');

        // Nama program tidak boleh dobel walau dari area lain
        $this->postJson('/api/programs', ['name' => 'PROGRAM DSA AGUSTUS 2026'], ['X-Area' => 'jateng'])->assertStatus(422);

        $jabarPrograms = collect($this->getJson('/api/programs/options', ['X-Area' => 'jabar'])->json('data'))->pluck('name');
        $jatengPrograms = collect($this->getJson('/api/programs/options', ['X-Area' => 'jateng'])->json('data'))->pluck('name');
        $this->assertContains('PROGRAM DSA SEPTEMBER 2026', $jabarPrograms);
        $this->assertEquals($jabarPrograms, $jatengPrograms);
    }

    public function test_invoices_follow_their_draft_area_and_cn_numbers_stay_unique_across_areas(): void
    {
        Http::fake();
        $draft = fn (string $area, string $dealer) => Draft::create(['area' => $area, 'dealer_name' => $dealer, 'cn_number' => '2608000043', 'support_amount' => 1000000, 'invoice_type' => 'DSA', 'status' => 'ready']);

        $jatengDraft = $draft('jateng', 'TOKO SOLO');
        $this->postJson("/api/invoices/generate/{$jatengDraft->id}", [], ['X-Area' => 'jateng'])->assertOk();

        $this->assertDatabaseHas('invoices', ['draft_id' => $jatengDraft->id, 'area' => 'jateng', 'invoice_number' => '2608000043']);
        $this->getJson('/api/invoices', ['X-Area' => 'jateng'])->assertJsonPath('total', 1);
        $this->getJson('/api/invoices', ['X-Area' => 'jabar'])->assertJsonPath('total', 0);

        // No CN yang sudah dipakai invoice Jawa Tengah tidak boleh dipakai lagi dari halaman Jawa Barat
        $jabarDraft = $draft('jabar', 'TOKO BANDUNG');
        $this->postJson("/api/invoices/generate/{$jabarDraft->id}", [], ['X-Area' => 'jabar'])->assertStatus(422);
        $this->assertSame('ready', $jabarDraft->fresh()->status);
    }

    public function test_dealers_drafts_and_reconciliation_history_are_listed_per_area(): void
    {
        Dealer::create(['area' => 'jabar', 'id_realme' => 'IDJABAR', 'dealer_name' => 'DEALER BANDUNG']);
        Dealer::create(['area' => 'jateng', 'id_realme' => 'IDJATENG', 'dealer_name' => 'DEALER SOLO']);
        Draft::create(['area' => 'jabar', 'dealer_name' => 'DRAFT BANDUNG', 'program_name' => 'P', 'support_amount' => 1, 'status' => 'ready']);
        $jatengDraft = Draft::create(['area' => 'jateng', 'dealer_name' => 'DRAFT SOLO', 'program_name' => 'P', 'support_amount' => 1, 'status' => 'ready']);
        $jatengDp = DataProgram::create(['area' => 'jateng', 'row_hash' => 'row_2', 'dealer_name' => 'DEALER SOLO']);
        ProgramReconciliationLog::create(['data_program_id' => $jatengDp->id, 'dealer_name' => 'DEALER SOLO', 'status' => ProgramReconciliationLog::STATUS_NO_MATCH]);

        $this->assertSame(['DEALER SOLO'], collect($this->getJson('/api/dealers', ['X-Area' => 'jateng'])->json('data.data'))->pluck('dealer_name')->all());
        $this->assertSame(['DRAFT SOLO'], collect($this->getJson('/api/drafts', ['X-Area' => 'jateng'])->json('data'))->pluck('dealer_name')->all());

        // Log rekonsiliasi otomatis mengikuti area Data Program-nya
        $this->assertDatabaseHas('program_reconciliation_logs', ['data_program_id' => $jatengDp->id, 'area' => 'jateng']);

        // Halaman detail draft (tanpa area) tetap bisa membuka draft area mana pun
        $this->getJson("/api/drafts/{$jatengDraft->id}")->assertOk();
    }

    public function test_spreadsheet_links_are_saved_per_area(): void
    {
        // Area yang belum diatur tidak boleh memakai spreadsheet Jawa Barat
        $this->getJson('/api/data-program/url', ['X-Area' => 'jateng'])
            ->assertJsonPath('url', '')
            ->assertJsonPath('webapp_url', '');

        $this->postJson('/api/data-program/url', [
            'url' => 'https://docs.google.com/spreadsheets/d/JATENG_SHEET/edit',
            'webapp_url' => 'https://script.google.com/macros/s/JATENG_APP/exec',
        ], ['X-Area' => 'jateng'])->assertOk();

        $this->getJson('/api/data-program/url', ['X-Area' => 'jateng'])
            ->assertJsonPath('url', 'https://docs.google.com/spreadsheets/d/JATENG_SHEET/edit')
            ->assertJsonPath('webapp_url', 'https://script.google.com/macros/s/JATENG_APP/exec');

        // Jawa Barat tetap memakai link lamanya sendiri
        $jabar = $this->getJson('/api/data-program/url', ['X-Area' => 'jabar'])->assertOk();
        $this->assertNotEmpty($jabar->json('url'));
        $this->assertStringNotContainsString('JATENG', $jabar->json('url'));
        $this->assertStringNotContainsString('JATENG', $jabar->json('webapp_url'));
    }

    public function test_draft_of_an_area_is_sent_to_that_area_data_program_and_spreadsheet(): void
    {
        Http::fake();
        Cache::forever(Area::Jateng->settingKey(DataProgramSyncService::CACHE_KEY_WEBAPP_URL), 'https://script.google.com/macros/s/JATENG_APP/exec');

        $draftId = $this->postJson('/api/drafts', [
            'cn_number' => 'CN-AREA-001',
            'area' => 'jateng',
            'dealer_name' => 'TOKO PURWOKERTO',
            'support_amount' => 1000000,
            'program_name' => 'PROGRAM DSA AGUSTUS 2026',
            'invoice_type' => 'DSA',
        ])->assertCreated()->assertJsonPath('data.area', 'jateng')->json('data.id');

        $this->postJson("/api/invoices/generate/{$draftId}")->assertOk();

        $this->assertDatabaseHas('data_programs', ['draft_id' => $draftId, 'area' => 'jateng']);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'JATENG_APP'));
        Http::assertNotSent(fn (Request $request) => $request->url() === DataProgramSyncService::DEFAULT_WEBAPP_URL);
        $this->assertSame('jateng', Draft::find($draftId)->area);
    }

    public function test_data_program_and_submission_from_different_areas_never_match(): void
    {
        $attributes = ['dealer_name' => 'TOKO SAMA', 'kode_bt' => 'BT001', 'program_name' => 'PROGRAM DSA AGUSTUS 2026', 'region' => 'BIG SOLO'];

        $dataProgram = DataProgram::create($attributes + ['area' => 'jateng', 'row_hash' => 'row_2']);
        $jabarSubmission = ProgramSubmission::create([
            'area' => 'jabar',
            'row_hash' => sha1('sub-jabar'),
            'dealer_name' => 'TOKO SAMA',
            'id_real' => 'BT001',
            'program_name' => 'PROGRAM DSA AGUSTUS 2026',
            'region' => 'BIG SOLO',
        ]);
        $jatengSubmission = $jabarSubmission->replicate()->fill(['area' => 'jateng', 'row_hash' => sha1('sub-jateng')]);
        $jatengSubmission->save();

        $service = app(ProgramReconciliationService::class);

        $this->assertNull($service->evaluateMatchScore($dataProgram, $jabarSubmission));
        $this->assertNotNull($service->evaluateMatchScore($dataProgram, $jatengSubmission));
        $this->assertSame($jatengSubmission->id, $service->findMatchingSubmission($dataProgram)?->id);
    }
}
