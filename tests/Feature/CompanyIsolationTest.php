<?php

namespace Tests\Feature;

use App\Jobs\ImportDealerFile;
use App\Models\Customer;
use App\Models\DataProgram;
use App\Models\Dealer;
use App\Models\GoogleToken;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CompanyCache;
use App\Services\CompanyContext;
use App\Services\CustomerLookupService;
use App\Services\DataResetService;
use App\Services\DealerImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function msi(callable $callback): mixed
    {
        return app(CompanyContext::class)->run(2, $callback(...));
    }

    public function test_users_only_open_the_companies_assigned_to_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $this->getJson('/api/companies', ['X-Company' => 'msi'])->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonFragment(['slug' => 'msi', 'name' => 'PT MEDIA SELULAR INDONESIA']);
        $this->getJson('/api/drafts', ['X-Company' => 'msi'])->assertOk();
        $this->getJson('/api/drafts')->assertOk();

        $topOnly = User::factory()->create(['role' => 'scm', 'company_slugs' => ['top']]);
        $this->actingAs($topOnly);
        $this->getJson('/api/companies')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'top');
        $this->getJson('/api/drafts', ['X-Company' => 'msi'])->assertForbidden();
        $this->getJson('/api/drafts', ['X-Company' => 'unknown'])->assertNotFound();
        $this->getJson('/api/drafts')->assertOk();

        $msiOnly = User::factory()->create(['role' => 'ar', 'company_slugs' => ['msi']]);
        $this->actingAs($msiOnly);
        $this->getJson('/api/companies', ['X-Company' => 'top'])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'msi');
        $this->getJson('/api/drafts', ['X-Company' => 'top'])->assertForbidden();
        $this->getJson('/api/drafts', ['X-Company' => 'msi'])->assertOk();

        $both = User::factory()->create(['role' => 'pajak', 'company_slugs' => ['top', 'msi']]);
        $this->actingAs($both);
        $this->getJson('/api/companies')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/data-program', ['X-Company' => 'top'])->assertOk();
        $this->getJson('/api/data-program', ['X-Company' => 'msi'])->assertOk();
    }

    public function test_brand_lists_and_public_lookup_follow_the_requested_company(): void
    {
        $this->getJson('/api/brands')->assertJsonPath('data.0.slug', 'realme');
        $brands = $this->getJson('/api/brands?company=msi')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing(['itel', 'tecno', 'villaon', 'motorola', 'motopad'], array_column($brands, 'slug'));

        $this->actingAs(User::factory()->create(['role' => 'scm', 'company_slugs' => ['top', 'msi']]));
        $this->getJson('/api/drafts', ['X-Company' => 'msi', 'X-Brand' => 'realme'])->assertUnprocessable();
        $this->getJson('/api/drafts', ['X-Company' => 'top', 'X-Brand' => 'itel'])->assertUnprocessable();
    }

    public function test_master_codes_can_repeat_between_companies_and_updates_cannot_cross_the_boundary(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $top = $this->postJson('/api/dealers', ['kode_bt' => 'BT001', 'dealer_name' => 'Top Dealer'])
            ->assertCreated()->json('data.id');
        $msi = $this->postJson('/api/dealers', ['kode_bt' => 'BT001', 'dealer_name' => 'MSI Dealer', 'company_id' => 1], ['X-Company' => 'msi'])
            ->assertCreated()->json('data.id');
        $this->assertDatabaseHas('dealers', ['id' => $top, 'company_id' => 1, 'dealer_name' => 'Top Dealer']);
        $this->assertDatabaseHas('dealers', ['id' => $msi, 'company_id' => 2]);
        $this->deleteJson("/api/dealers/{$top}", [], ['X-Company' => 'msi'])->assertNotFound();
        $this->putJson("/api/dealers/{$top}", ['kode_bt' => 'BT001', 'dealer_name' => 'Changed'], ['X-Company' => 'msi'])->assertNotFound();
        $this->postJson('/api/dealers', ['kode_bt' => 'BT001', 'dealer_name' => 'Duplicate'], ['X-Company' => 'msi'])
            ->assertUnprocessable()->assertJsonValidationErrors('kode_bt');
    }

    public function test_same_cn_generates_separate_invoices_and_pdf_files_without_cross_company_access(): void
    {
        Http::fake();
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'scm', 'company_slugs' => ['top', 'msi']]));
        $payload = ['cn_number' => 'CN-100', 'dealer_name' => 'Dealer', 'support_amount' => 1000000, 'program_name' => 'Program', 'invoice_type' => 'DSA'];
        $top = $this->postJson('/api/drafts', $payload)->assertCreated()->json('data.id');
        $msi = $this->postJson('/api/drafts', $payload, ['X-Company' => 'msi'])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('drafts', ['id' => $msi, 'company_id' => 2, 'brand' => 'itel']);
        $this->postJson("/api/invoices/generate/{$top}")->assertOk();
        $this->msi(fn () => Customer::create(['code' => 'PT MSI', 'name' => 'PT MSI', 'is_active' => true]));
        $this->postJson("/api/invoices/generate/{$msi}", ['bill_to' => 'PT MSI'], ['X-Company' => 'msi'])->assertOk();
        $topInvoice = Invoice::firstOrFail();
        $msiInvoice = $this->msi(fn () => Invoice::firstOrFail());
        $this->assertNotSame($topInvoice->pdf_path, $msiInvoice->pdf_path);
        Storage::disk('public')->assertExists([$topInvoice->pdf_path, $msiInvoice->pdf_path]);
        $this->getJson("/api/invoices/{$topInvoice->id}", ['X-Company' => 'msi'])->assertNotFound();
        $this->get("/invoices/{$msiInvoice->id}/preview?company=msi")->assertOk();
        $this->get("/invoices/{$msiInvoice->id}/preview")->assertNotFound();
        $this->postJson('/api/invoices/delete-batch', ['ids' => [$topInvoice->id]], ['X-Company' => 'msi'])
            ->assertUnprocessable();
        $this->assertDatabaseHas('invoices', ['id' => $topInvoice->id]);
        $this->msi(fn () => DataProgram::query()->update(['cek_dokumen' => 'LENGKAP']));
        $this->assertSame(0, Invoice::whereUploadedToForm()->count());
        $this->assertSame(1, Invoice::whereNotUploadedToForm()->count());
        $this->assertSame(1, $this->msi(fn () => Invoice::whereUploadedToForm()->count()));
        $this->msi(fn () => app(DataResetService::class)->reset(['drafts', 'invoices'], null, null));
        $this->assertDatabaseHas('drafts', ['id' => $top]);
        $this->assertDatabaseMissing('drafts', ['id' => $msi]);
    }

    public function test_settings_and_queued_import_status_are_isolated_and_failure_restores_context(): void
    {
        Queue::fake();
        Storage::fake('local');
        CompanyCache::forever('setting', 'top-value');
        $this->msi(function (): void {
            $this->assertNull(CompanyCache::get('setting'));
            CompanyCache::forever('setting', 'msi-value');
            ImportDealerFile::start(UploadedFile::fake()->create('msi.xlsx'), false, 'SCM', 'dealers');
            $this->assertSame('queued', ImportDealerFile::status()['state']);
        });
        $this->assertNull(ImportDealerFile::status());
        $this->assertSame('top-value', CompanyCache::get('setting'));
        Queue::assertPushed(ImportDealerFile::class, function (ImportDealerFile $job): bool {
            $this->assertSame(2, $job->companyId);
            $restored = unserialize(serialize($job));
            $restored->failed(new \RuntimeException('Worker stopped'));
            $this->assertSame(1, app(CompanyContext::class)->id());
            $this->assertNull(ImportDealerFile::status());
            $this->assertSame('failed', $this->msi(fn () => ImportDealerFile::status()['state']));

            return true;
        });
    }

    public function test_replace_import_does_not_replace_the_other_company_dealers(): void
    {
        Dealer::create(['kode_bt' => 'BT001', 'dealer_name' => 'Top Dealer']);
        $this->msi(fn () => Dealer::create(['kode_bt' => 'OLD', 'dealer_name' => 'Old MSI']));
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray([
            ['ID Customer', 'Nama Customer', 'Big Area', 'Reffnote'],
            ['BT001', 'MSI Dealer', 'JABAR', 'REALME - DP BANDUNG'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'msi-import').'.xlsx';
        try {
            (new Xlsx($sheet))->save($path);
            $this->msi(fn () => app(DealerImportService::class)->import($path, true));
        } finally {
            @unlink($path);
        }
        $this->assertDatabaseHas('dealers', ['company_id' => 1, 'kode_bt' => 'BT001', 'dealer_name' => 'Top Dealer']);
        $this->assertDatabaseHas('dealers', ['company_id' => 2, 'kode_bt' => 'BT001', 'dealer_name' => 'MSI Dealer']);
        $this->assertDatabaseMissing('dealers', ['company_id' => 2, 'kode_bt' => 'OLD']);
    }

    public function test_msi_does_not_fall_back_to_legacy_customer_identity_or_phone_numbers(): void
    {
        config(['services.wag.ar_phone' => '62811111111']);
        $this->msi(function (): void {
            $this->assertSame([], CustomerLookupService::getBillToOptions());
            $this->assertNull(CustomerLookupService::getBillTo('CV TOP'));
            $this->assertSame('-', CustomerLookupService::lookup('PT MSI')['npwp']);
            $this->assertSame('', config('services.wag.ar_phone'));
            Customer::create(['code' => 'PT MSI', 'name' => 'PT MSI', 'is_active' => true]);
            $this->assertSame('PT MSI', CustomerLookupService::getBillToOptions()[0]['name']);
        });
        $this->assertSame('62811111111', config('services.wag.ar_phone'));
        $this->assertFalse(Customer::where('code', 'PT MSI')->exists());
    }

    public function test_full_spreadsheet_sync_and_background_commands_only_change_their_company(): void
    {
        Http::fake();
        $top = DataProgram::create(['row_hash' => 'row_2', 'dealer_name' => 'TOP', 'tgl_share_cn' => '01/11/2026', 'lama_pending' => '0']);
        $this->postJson('/api/webhooks/data-program?company=msi', [
            'start_row' => 2, 'is_full_sync' => true,
            'rows' => [['MSI DEALER', 'PROGRAM 1', 'BT001', 'PROGRAM DSA AGUSTUS 2026', '2026', 'BIG BANDUNG']],
        ])->assertOk()->assertJsonPath('saved_count', 1);
        $this->assertDatabaseHas('data_programs', ['id' => $top->id, 'company_id' => 1, 'dealer_name' => 'TOP']);
        $this->assertDatabaseHas('data_programs', ['company_id' => 2, 'row_hash' => 'row_2', 'dealer_name' => 'MSI DEALER']);
        $msi = $this->msi(fn () => DataProgram::firstOrFail());
        $this->msi(fn () => $msi->update(['tgl_share_cn' => '01/11/2026', 'lama_pending' => '0']));
        $this->travelTo(now()->setDate(2026, 11, 25));
        $this->artisan('company:run', ['company' => 'msi', 'task' => 'pending'])->assertSuccessful();
        $this->assertSame('0', $top->fresh()->getRawOriginal('lama_pending'));
        $this->assertSame('24', $this->msi(fn () => $msi->fresh()->getRawOriginal('lama_pending')));
        $this->assertSame(1, app(CompanyContext::class)->id());
    }

    public function test_disconnecting_msi_google_account_keeps_the_top_account(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $top = GoogleToken::create(['account_email' => 'top@example.com', 'access_token' => 'top-token']);
        $msi = $this->msi(fn () => GoogleToken::create(['account_email' => 'msi@example.com', 'access_token' => 'msi-token']));
        $this->postJson('/api/google/disconnect', [], ['X-Company' => 'msi'])->assertOk();
        $this->assertDatabaseHas('google_tokens', ['id' => $top->id]);
        $this->assertDatabaseMissing('google_tokens', ['id' => $msi->id]);
    }
}
