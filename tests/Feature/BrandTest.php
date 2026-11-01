<?php

namespace Tests\Feature;

use App\Area;
use App\Mail\InvoiceMail;
use App\Models\Brand;
use App\Models\DataProgram;
use App\Models\Draft;
use App\Models\MasterProgram;
use App\Models\ProgramReconciliationLog;
use App\Models\ProgramSubmission;
use App\Models\User;
use App\Services\DataProgramSyncService;
use App\Services\ProgramReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // API selain login, form publik & webhook butuh login
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_new_brand_gets_a_url_slug_that_stays_when_renamed(): void
    {
        $this->getJson('/api/brands')->assertOk()->assertJsonPath('data.0.slug', 'realme');

        $id = $this->postJson('/api/brands', ['name' => 'Oppo'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'oppo')
            ->json('data.id');

        $this->postJson('/api/brands', ['name' => 'Oppo'])->assertStatus(422)->assertJsonValidationErrors(['name']);

        $this->putJson("/api/brands/{$id}", ['name' => 'OPPO Indonesia'])
            ->assertOk()
            ->assertJsonPath('data.name', 'OPPO Indonesia')
            ->assertJsonPath('data.slug', 'oppo');
    }

    public function test_brand_with_data_cannot_be_deleted_and_the_last_active_brand_stays_active(): void
    {
        $realme = Brand::where('slug', Brand::DEFAULT_SLUG)->first();
        $this->deleteJson("/api/brands/{$realme->id}")->assertStatus(422);
        $this->patchJson("/api/brands/{$realme->id}/toggle-status")->assertStatus(422);

        $oppo = Brand::factory()->create(['slug' => 'oppo', 'name' => 'Oppo']);
        Draft::create(['brand' => 'oppo', 'dealer_name' => 'TOKO OPPO', 'support_amount' => 1, 'status' => 'ready']);
        $this->deleteJson("/api/brands/{$oppo->id}")->assertStatus(422);
        $this->assertDatabaseHas('brands', ['slug' => 'oppo']);

        $vivo = Brand::factory()->create(['slug' => 'vivo', 'name' => 'Vivo']);
        $this->deleteJson("/api/brands/{$vivo->id}")->assertOk();
        $this->assertDatabaseMissing('brands', ['slug' => 'vivo']);
    }

    public function test_lists_only_show_the_selected_brand_and_new_records_get_it(): void
    {
        Brand::factory()->create(['slug' => 'oppo', 'name' => 'Oppo']);
        Draft::create(['dealer_name' => 'DRAFT REALME', 'support_amount' => 1, 'status' => 'ready']);
        DataProgram::create(['brand' => 'oppo', 'row_hash' => 'row_2', 'dealer_name' => 'DP OPPO']);
        DataProgram::create(['row_hash' => 'row_2', 'dealer_name' => 'DP REALME']);

        $this->assertSame('realme', Draft::first()->brand);
        $this->assertSame(['DP OPPO'], collect($this->getJson('/api/data-program', ['X-Brand' => 'oppo'])->json('items'))->pluck('dealer_name')->all());
        $this->assertSame(['DP REALME'], collect($this->getJson('/api/data-program', ['X-Brand' => 'realme'])->json('items'))->pluck('dealer_name')->all());
        $this->assertSame([], $this->getJson('/api/drafts', ['X-Brand' => 'oppo'])->json('data'));

        $this->postJson('/api/drafts', [
            'cn_number' => 'CN-OPPO-001',
            'dealer_name' => 'TOKO OPPO',
            'support_amount' => 1000000,
            'program_name' => 'PROGRAM OPPO AGUSTUS 2026',
            'invoice_type' => 'DSA',
        ], ['X-Brand' => 'oppo'])->assertCreated()->assertJsonPath('data.brand', 'oppo');
    }

    public function test_invoice_and_data_program_of_a_draft_follow_its_brand_and_cn_numbers_stay_unique_across_brands(): void
    {
        Http::fake();
        Brand::factory()->create(['slug' => 'oppo', 'name' => 'Oppo']);
        $draft = fn (string $brand, string $dealer) => Draft::create(['brand' => $brand, 'dealer_name' => $dealer, 'cn_number' => '2608000099', 'support_amount' => 1000000, 'invoice_type' => 'DSA', 'status' => 'ready']);

        $oppoDraft = $draft('oppo', 'TOKO OPPO');
        $this->postJson("/api/invoices/generate/{$oppoDraft->id}", [], ['X-Brand' => 'oppo'])->assertOk();

        $this->assertDatabaseHas('invoices', ['draft_id' => $oppoDraft->id, 'brand' => 'oppo']);
        $this->assertDatabaseHas('data_programs', ['draft_id' => $oppoDraft->id, 'brand' => 'oppo']);
        $this->getJson('/api/invoices', ['X-Brand' => 'realme'])->assertJsonPath('total', 0);

        // Oppo belum punya spreadsheet: barisnya tidak boleh terkirim ke spreadsheet Realme
        Http::assertNotSent(fn (Request $request) => $request->url() === DataProgramSyncService::DEFAULT_WEBAPP_URL);

        $realmeDraft = $draft('realme', 'TOKO REALME');
        $this->postJson("/api/invoices/generate/{$realmeDraft->id}", [], ['X-Brand' => 'realme'])->assertStatus(422);
    }

    public function test_spreadsheet_links_are_saved_per_brand(): void
    {
        Brand::factory()->create(['slug' => 'oppo', 'name' => 'Oppo']);

        $this->getJson('/api/data-program/url', ['X-Brand' => 'oppo'])
            ->assertJsonPath('url', '')
            ->assertJsonPath('webapp_url', '');

        $this->postJson('/api/data-program/url', [
            'url' => 'https://docs.google.com/spreadsheets/d/OPPO_SHEET/edit',
            'webapp_url' => 'https://script.google.com/macros/s/OPPO_APP/exec',
        ], ['X-Brand' => 'oppo'])->assertOk();

        $this->getJson('/api/data-program/url', ['X-Brand' => 'oppo'])
            ->assertJsonPath('webapp_url', 'https://script.google.com/macros/s/OPPO_APP/exec');

        // Realme Jawa Barat tetap memakai link lamanya
        $this->getJson('/api/data-program/url', ['X-Brand' => 'realme'])
            ->assertJsonPath('webapp_url', DataProgramSyncService::DEFAULT_WEBAPP_URL);
        $this->assertSame('https://script.google.com/macros/s/OPPO_APP/exec', Cache::get(Brand::settingKey(DataProgramSyncService::CACHE_KEY_WEBAPP_URL, Area::Jabar, 'oppo')));
    }

    public function test_full_sync_of_the_realme_sheet_keeps_other_brand_rows(): void
    {
        Http::fake();
        Brand::factory()->create(['slug' => 'oppo', 'name' => 'Oppo']);
        DataProgram::create(['brand' => 'oppo', 'row_hash' => 'row_2', 'dealer_name' => 'DP OPPO BARIS 2']);
        DataProgram::create(['brand' => 'oppo', 'row_hash' => 'draft_7', 'dealer_name' => 'DP OPPO DRAFT']);

        $this->postJson('/api/webhooks/data-program', [
            'start_row' => 2,
            'is_full_sync' => true,
            'rows' => [['DEALER REALME', 'PROGRAM 1', 'BT001', 'PROGRAM DSA AGUSTUS 2026', '2026', 'BIG BANDUNG']],
        ])->assertOk()->assertJsonPath('saved_count', 1);

        $this->assertDatabaseHas('data_programs', ['brand' => 'realme', 'row_hash' => 'row_2', 'dealer_name' => 'DEALER REALME']);
        $this->assertDatabaseHas('data_programs', ['brand' => 'oppo', 'row_hash' => 'row_2', 'dealer_name' => 'DP OPPO BARIS 2']);
        $this->assertDatabaseHas('data_programs', ['brand' => 'oppo', 'row_hash' => 'draft_7']);
    }

    public function test_data_program_and_submission_of_different_brands_never_match(): void
    {
        Brand::factory()->create(['slug' => 'oppo', 'name' => 'Oppo']);
        $attributes = ['dealer_name' => 'TOKO SAMA', 'program_name' => 'PROGRAM DSA AGUSTUS 2026', 'region' => 'BIG BANDUNG'];

        $dataProgram = DataProgram::create($attributes + ['brand' => 'oppo', 'row_hash' => 'row_2', 'kode_bt' => 'BT001']);
        $realmeSubmission = ProgramSubmission::create($attributes + ['row_hash' => sha1('sub-realme'), 'id_real' => 'BT001']);
        $oppoSubmission = ProgramSubmission::create($attributes + ['brand' => 'oppo', 'row_hash' => sha1('sub-oppo'), 'id_real' => 'BT001']);

        $service = app(ProgramReconciliationService::class);

        $this->assertNull($service->evaluateMatchScore($dataProgram, $realmeSubmission));
        $this->assertNotNull($service->evaluateMatchScore($dataProgram, $oppoSubmission));
        $this->assertSame($oppoSubmission->id, $service->findMatchingSubmission($dataProgram)?->id);

        // Riwayat pencocokan ikut brand Data Program-nya
        $log = ProgramReconciliationLog::create(['data_program_id' => $dataProgram->id, 'dealer_name' => 'TOKO SAMA', 'status' => ProgramReconciliationLog::STATUS_NO_MATCH]);
        $this->assertSame('oppo', $log->brand);
    }

    public function test_master_program_codes_are_per_brand_and_public_form_shows_only_its_brand_programs(): void
    {
        Brand::factory()->create(['slug' => 'oppo', 'name' => 'Oppo']);
        MasterProgram::create(['code' => 'PROGRAM 1', 'name' => 'PROGRAM REALME DSA', 'is_active' => true]);

        $this->postJson('/api/programs', ['code' => 'PROGRAM 1', 'name' => 'PROGRAM OPPO DSA'], ['X-Brand' => 'oppo'])->assertCreated();
        $this->postJson('/api/programs', ['code' => 'PROGRAM 1', 'name' => 'PROGRAM REALME LAIN'], ['X-Brand' => 'realme'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->getJson('/api/program-submissions/form-options', ['X-Brand' => 'oppo', 'X-Area' => 'jabar'])
            ->assertOk()
            ->assertJsonPath('programs', ['PROGRAM OPPO DSA'])
            ->assertJsonPath('brand.label', 'Oppo');
    }

    public function test_invoice_email_links_dealers_to_the_public_form_of_its_brand_and_area(): void
    {
        config(['services.program_form.url' => 'https://scm-customer.oceanspace.co.id/']);
        Brand::factory()->create(['slug' => 'oppo', 'name' => 'Oppo']);
        $draft = Draft::create(['brand' => 'oppo', 'area' => 'jateng', 'dealer_name' => 'TOKO OPPO', 'support_amount' => 1, 'status' => 'ready']);
        $invoice = $draft->invoice()->create(['brand' => 'oppo', 'area' => 'jateng', 'invoice_number' => 'CN-1', 'dealer_name' => 'TOKO OPPO', 'invoice_type' => 'DSA']);

        $this->assertSame('Oppo', $invoice->brandName());
        $this->assertSame('https://scm-customer.oceanspace.co.id/form/oppo/jateng', $invoice->documentUploadUrl());

        $invoice->update(['brand' => 'realme', 'area' => 'jabar']);
        $realmeJabar = $invoice->fresh();
        $this->assertSame('https://scm-customer.oceanspace.co.id/form/realme/jabar', $realmeJabar->documentUploadUrl());
        $this->assertStringContainsString('https://scm-customer.oceanspace.co.id/form/realme/jabar', (new InvoiceMail($realmeJabar))->render());
    }
}
