<?php

namespace Tests\Feature;

use App\Models\DataProgram;
use App\Models\Dealer;
use App\Models\Draft;
use App\Models\Invoice;
use App\Models\MasterProgram;
use App\Models\ProgramSubmission;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EditAccessTest extends TestCase
{
    use RefreshDatabase;

    private const VIEW_ONLY_ROLES = ['ar', 'telemarketing'];

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        Mail::fake();
    }

    public function test_ar_and_telemarketing_cannot_change_data(): void
    {
        $dealer = Dealer::create(['area' => 'jabar', 'kode_bt' => 'BT01111', 'dealer_name' => 'TOKO LAMA', 'is_active' => true]);
        $draft = Draft::create(['area' => 'jabar', 'dealer_name' => 'TOKO LAMA', 'cn_number' => 'CN-001', 'support_amount' => 1000, 'invoice_type' => 'DSA', 'status' => 'invoiced']);
        $invoice = Invoice::create(['area' => 'jabar', 'draft_id' => $draft->id, 'invoice_number' => 'CN-001', 'invoice_type' => 'DSA', 'email' => 'toko@example.com', 'status' => 'generated']);
        $dataProgram = DataProgram::create(['area' => 'jabar', 'row_hash' => 'row_2', 'dealer_name' => 'TOKO LAMA']);
        $submission = ProgramSubmission::create(['area' => 'jabar', 'row_hash' => 'sub_1', 'dealer_name' => 'TOKO LAMA']);
        $program = MasterProgram::create(['brand' => 'realme', 'name' => 'PROGRAM JUNI 2026']);

        foreach (self::VIEW_ONLY_ROLES as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'permissions' => User::getDefaultPermissionsForRole($role)]));

            $writes = [
                ['PUT', "/api/dealers/{$dealer->id}", ['kode_bt' => 'BT01111', 'dealer_name' => 'TOKO DIUBAH']],
                ['POST', '/api/drafts', ['cn_number' => 'CN-002', 'dealer_name' => 'TOKO BARU', 'support_amount' => 1000, 'invoice_type' => 'DSA']],
                ['POST', '/api/invoices/quick-send-all', []],
                ['PUT', "/api/data-program/{$dataProgram->id}", ['dealer_name' => 'TOKO DIUBAH', 'push_to_sheet' => false]],
                ['PATCH', "/api/program-submissions/{$submission->id}", ['dealer_name' => 'TOKO DIUBAH']],
                ['DELETE', "/api/programs/{$program->id}", []],
                ['POST', '/api/users', ['name' => 'Akun Baru', 'email' => 'baru@example.com', 'role' => 'scm', 'password' => 'rahasia123']],
                ['POST', '/api/regions', ['name' => 'BIG SOLO']],
            ];

            foreach ($writes as [$method, $uri, $data]) {
                $this->json($method, $uri, $data, ['X-Area' => 'jabar'])
                    ->assertForbidden()
                    ->assertJsonPath('message', 'Hanya Admin dan SCM yang bisa mengubah data.');
            }
        }

        $this->assertSame('TOKO LAMA', $dealer->fresh()->dealer_name);
        $this->assertSame(1, Draft::count());
        $this->assertNull($invoice->fresh()->email_sent_at);
        $this->assertSame('TOKO LAMA', $dataProgram->fresh()->dealer_name);
        $this->assertSame('TOKO LAMA', $submission->fresh()->dealer_name);
        $this->assertModelExists($program);
        $this->assertDatabaseMissing('users', ['email' => 'baru@example.com']);
        $this->assertFalse(Region::where('name', 'BIG SOLO')->exists());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_ar_and_telemarketing_can_still_open_their_menus(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'ar', 'permissions' => User::getDefaultPermissionsForRole('ar')]));
        $this->getJson('/api/drafts')->assertOk();
        $this->getJson('/api/invoices')->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'telemarketing', 'permissions' => User::getDefaultPermissionsForRole('telemarketing')]));
        $this->getJson('/api/program-submissions')->assertOk();
        $this->getJson('/api/data-program/approval-history')->assertOk();
    }

    public function test_ar_and_telemarketing_can_still_change_their_own_profile(): void
    {
        Storage::fake('public');

        foreach (self::VIEW_ONLY_ROLES as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user);

            $this->putJson('/api/profile', ['name' => "Nama {$role}", 'email' => "{$role}@example.com"])->assertOk();
            $this->putJson('/api/profile/password', ['current_password' => 'password', 'password' => 'rahasiabaru2026', 'password_confirmation' => 'rahasiabaru2026'])->assertOk();
            $this->post('/api/profile/photo', ['photo' => UploadedFile::fake()->image('foto.jpg')], ['Accept' => 'application/json'])->assertOk();
            $this->deleteJson('/api/profile/photo')->assertOk();

            $this->assertSame("Nama {$role}", $user->fresh()->name);
        }
    }

    public function test_admin_and_scm_can_still_change_data(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'scm']));
        $this->postJson('/api/regions', ['name' => 'BIG SOLO'], ['X-Area' => 'jateng'])->assertCreated();

        $program = MasterProgram::create(['brand' => 'realme', 'name' => 'PROGRAM JUNI 2026']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->deleteJson("/api/programs/{$program->id}")->assertOk();

        $this->assertTrue(Region::where('area', 'jateng')->where('name', 'BIG SOLO')->exists());
        $this->assertModelMissing($program);

        // Akun tanpa peran dianggap SCM; akun nonaktif tidak bisa mengubah data
        $this->assertTrue(User::factory()->make(['role' => null])->canEdit());
        $this->assertFalse(User::factory()->make(['role' => 'scm', 'is_active' => false])->canEdit());
    }

    public function test_ar_and_telemarketing_cannot_connect_the_google_account_that_sends_invoices(): void
    {
        Http::preventStrayRequests();

        foreach (['ar', 'telemarketing'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));

            $this->get('/auth/google/redirect')->assertRedirectContains('google_error=');
            $this->get('/auth/google/callback?code=kode-dari-google')->assertRedirectContains('google_error=');
        }

        $this->assertDatabaseCount('google_tokens', 0);
    }
}
