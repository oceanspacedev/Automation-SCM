<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Brand;
use App\Models\DataProgram;
use App\Models\Dealer;
use App\Models\Draft;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\ProgramReconciliationLog;
use App\Models\ProgramSubmission;
use App\Models\User;
use App\Models\WhatsAppLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DataResetTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'rahasia-reset';

    protected function setUp(): void
    {
        parent::setUp();

        AppSetting::write(AppSetting::DATA_RESET_PASSWORD, Hash::make(self::PASSWORD));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function invoicedDraft(string $area, string $cnNumber, string $brand = 'realme'): Draft
    {
        $draft = Draft::create(['area' => $area, 'brand' => $brand, 'dealer_name' => "TOKO {$cnNumber}", 'cn_number' => $cnNumber, 'support_amount' => 1000, 'status' => 'invoiced']);
        $invoice = Invoice::create(['area' => $area, 'brand' => $brand, 'draft_id' => $draft->id, 'invoice_number' => $cnNumber, 'invoice_type' => 'DSA']);
        EmailLog::create(['invoice_id' => $invoice->id, 'invoice_number' => $cnNumber, 'recipient_email' => 'toko@example.com']);
        WhatsAppLog::create(['invoice_id' => $invoice->id, 'invoice_number' => $cnNumber, 'recipient_phone' => '081200000001']);

        return $draft;
    }

    private function matchedProgram(string $area, string $brand, string $hash): DataProgram
    {
        $dataProgram = DataProgram::create(['area' => $area, 'brand' => $brand, 'row_hash' => "dp_{$hash}", 'dealer_name' => "TOKO {$hash}"]);
        $submission = ProgramSubmission::create(['area' => $area, 'brand' => $brand, 'row_hash' => "sub_{$hash}", 'dealer_name' => "TOKO {$hash}"]);
        ProgramReconciliationLog::create(['area' => $area, 'brand' => $brand, 'data_program_id' => $dataProgram->id, 'program_submission_id' => $submission->id, 'status' => 'MATCHED']);

        return $dataProgram;
    }

    public function test_only_an_admin_with_the_reset_password_can_reset_data(): void
    {
        $draft = $this->invoicedDraft('jabar', '2608000001');

        $this->getJson('/api/data-reset')->assertUnauthorized();

        $this->actingAs(User::factory()->create(['role' => 'scm']))
            ->postJson('/api/data-reset', ['datasets' => ['drafts'], 'password' => self::PASSWORD])
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->postJson('/api/data-reset', ['datasets' => ['drafts'], 'password' => 'tebak-tebak'])
            ->assertStatus(422)
            ->assertJsonPath('errors.password.0', 'Password reset salah.');

        // Selama password reset belum diatur, reset tidak bisa dipakai sama sekali
        AppSetting::query()->delete();
        $this->postJson('/api/data-reset', ['datasets' => ['drafts'], 'password' => 'apa-saja'])
            ->assertStatus(422)
            ->assertJsonPath('errors.password.0', 'Password reset belum diatur. Atur dulu lewat tombol "Atur password reset".');

        $this->assertModelExists($draft);
    }

    public function test_an_admin_sets_the_reset_password_and_needs_the_old_one_to_change_it(): void
    {
        AppSetting::query()->delete();
        $newPassword = fn (string $password, array $extra = []) => $extra + ['new_password' => $password, 'new_password_confirmation' => $password];

        $this->actingAs(User::factory()->create(['role' => 'scm']))
            ->putJson('/api/data-reset/password', $newPassword('pertama1'))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->getJson('/api/data-reset')
            ->assertJsonPath('password_set', false);

        $this->putJson('/api/data-reset/password', $newPassword('pertama1'))->assertOk();
        $this->getJson('/api/data-reset')->assertJsonPath('password_set', true);
        $this->assertTrue(Hash::check('pertama1', AppSetting::read(AppSetting::DATA_RESET_PASSWORD)));

        $this->putJson('/api/data-reset/password', $newPassword('kedua22', ['current_password' => 'salah']))
            ->assertStatus(422)
            ->assertJsonPath('errors.current_password.0', 'Password lama salah.');

        $this->putJson('/api/data-reset/password', $newPassword('kedua22', ['current_password' => 'pertama1']))->assertOk();
        $this->postJson('/api/data-reset', ['datasets' => ['drafts'], 'password' => 'kedua22'])->assertOk();
    }

    public function test_resetting_drafts_of_an_area_also_removes_their_invoices_and_logs_but_keeps_master_data(): void
    {
        $this->invoicedDraft('jabar', '2608000001');
        $jatengDraft = $this->invoicedDraft('jateng', '2608000002');
        Dealer::create(['area' => 'jabar', 'kode_bt' => 'BT01111', 'dealer_name' => 'TOKO MASTER', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->getJson('/api/data-reset?area=jabar')
            ->assertOk()
            ->assertJsonPath('counts.drafts', 1)
            ->assertJsonPath('counts.invoices', 1);

        $this->postJson('/api/data-reset', ['datasets' => ['drafts'], 'area' => 'jabar', 'brand' => 'all', 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonPath('deleted.drafts', 1)
            ->assertJsonPath('deleted.invoices', 1)
            ->assertJsonPath('deleted.history', 2)
            ->assertJsonPath('counts.drafts', 0);

        $this->assertSame([$jatengDraft->id], Draft::withoutGlobalScopes()->pluck('id')->all());
        $this->assertSame(1, Invoice::withoutGlobalScopes()->count());
        $this->assertSame(1, EmailLog::count());
        $this->assertSame(1, WhatsAppLog::count());
        $this->assertSame(1, Dealer::withoutGlobalScopes()->count());
    }

    public function test_resetting_only_invoices_lets_their_drafts_be_generated_again(): void
    {
        $draft = $this->invoicedDraft('jabar', '2608000001');

        $this->actingAs($this->admin())
            ->postJson('/api/data-reset', ['datasets' => ['invoices'], 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonPath('deleted.invoices', 1);

        $this->assertSame('ready', $draft->fresh()->status);
        $this->assertSame(0, Invoice::withoutGlobalScopes()->count());
    }

    public function test_resetting_program_data_of_a_brand_removes_its_matching_history_only(): void
    {
        Brand::factory()->create(['slug' => 'xiaomi', 'name' => 'Xiaomi']);
        $this->matchedProgram('jabar', 'realme', 'realme');
        $xiaomi = $this->matchedProgram('jabar', 'xiaomi', 'xiaomi');
        // Pengajuan yang sudah dihapus (soft delete) ikut dihapus permanen agar sinkron ulang tidak bentrok row_hash
        ProgramSubmission::create(['area' => 'jabar', 'brand' => 'realme', 'row_hash' => 'sub_trashed', 'dealer_name' => 'TOKO LAMA'])->delete();

        $this->actingAs($this->admin())
            ->postJson('/api/data-reset', ['datasets' => ['data_programs', 'program_submissions'], 'area' => 'all', 'brand' => 'realme', 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonPath('deleted.data_programs', 1)
            ->assertJsonPath('deleted.program_submissions', 1)
            ->assertJsonPath('deleted.history', 1);

        $this->assertSame([$xiaomi->id], DataProgram::withoutGlobalScopes()->pluck('id')->all());
        $this->assertSame(['xiaomi'], ProgramSubmission::withoutGlobalScopes()->pluck('brand')->all());
        $this->assertSame(['xiaomi'], ProgramReconciliationLog::withoutGlobalScopes()->pluck('brand')->all());
    }
}
