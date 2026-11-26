<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // API selain login, form publik & webhook butuh login
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_can_list_users_with_stats(): void
    {
        User::factory()->create(['role' => 'admin', 'is_active' => true]);
        User::factory()->create(['role' => 'scm', 'is_active' => true]);
        User::factory()->create(['role' => 'ar', 'is_active' => true]);
        User::factory()->create(['role' => 'telemarketing', 'is_active' => false]);
        User::factory()->create(['role' => 'pajak', 'is_active' => true]);

        $response = $this->getJson('/api/users');

        // Termasuk Admin yang login di setUp
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stats.total', 6)
            ->assertJsonPath('stats.admin', 2)
            ->assertJsonPath('stats.scm', 1)
            ->assertJsonPath('stats.ar', 1)
            ->assertJsonPath('stats.telemarketing', 1)
            ->assertJsonPath('stats.pajak', 1)
            ->assertJsonPath('stats.active', 5)
            ->assertJsonPath('stats.inactive', 1)
            ->assertJsonPath('default_permissions.pajak', [
                'data_program',
                'data_program_edit',
                'invoices',
                'riwayat_program',
            ]);
    }

    public function test_can_create_user_with_role_and_formatted_whatsapp(): void
    {
        $payload = [
            'name' => 'Budi SCM',
            'email' => 'budi@scm.test',
            'whatsapp' => '081234567890',
            'role' => 'scm',
            'password' => 'secret123',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/users', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'scm')
            ->assertJsonPath('data.whatsapp', '6281234567890');

        $this->assertDatabaseHas('users', [
            'email' => 'budi@scm.test',
            'role' => 'scm',
            'whatsapp' => '6281234567890',
        ]);
    }

    public function test_can_create_and_update_user_with_role_pajak(): void
    {
        $payload = [
            'name' => 'Staff Pajak',
            'email' => 'staff.pajak@scm.test',
            'whatsapp' => '081234567891',
            'role' => 'pajak',
            'password' => 'secret123',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/users', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'pajak');

        $userId = $response->json('data.id');

        $updateResponse = $this->putJson("/api/users/{$userId}", [
            'name' => 'Staff Pajak Senior',
            'email' => 'staff.pajak@scm.test',
            'role' => 'pajak',
            'is_active' => true,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Staff Pajak Senior')
            ->assertJsonPath('data.role', 'pajak');
    }

    public function test_accounts_can_be_saved_without_email(): void
    {
        $id = $this->postJson('/api/users', [
            'name' => 'Tele Tanpa Email',
            'email' => '',
            'whatsapp' => '081300000005',
            'role' => 'telemarketing',
            'password' => 'secret123',
        ])->assertCreated()->assertJsonPath('data.email', null)->json('data.id');

        $other = User::factory()->create(['role' => 'telemarketing']);
        $this->putJson("/api/users/{$other->id}", [
            'name' => $other->name,
            'email' => '',
            'whatsapp' => '081300000006',
            'role' => 'telemarketing',
        ])->assertOk();

        $this->assertSame([null, null], [User::find($id)->email, $other->fresh()->email]);
    }

    public function test_can_update_user(): void
    {
        $user = User::factory()->create([
            'role' => 'telemarketing',
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/users/{$user->id}", [
            'name' => 'Nama Baru',
            'email' => 'nama.baru@scm.test',
            'role' => 'ar',
            'whatsapp' => '08987654321',
            'is_active' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'ar');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'ar',
            'whatsapp' => '628987654321',
        ]);
    }

    public function test_can_toggle_user_status(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => 'ar']);

        $response = $this->patchJson("/api/users/{$user->id}/toggle-status");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => false,
        ]);
    }

    public function test_can_set_custom_permissions_for_user(): void
    {
        $payload = [
            'name' => 'Custom User',
            'email' => 'custom@scm.test',
            'role' => 'ar',
            'permissions' => ['drafts', 'invoices', 'data_program'],
            'password' => 'secret123',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/users', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.permissions', ['drafts', 'invoices', 'data_program']);

        $user = User::where('email', 'custom@scm.test')->first();
        $this->assertTrue($user->hasPermission('drafts'));
        $this->assertTrue($user->hasPermission('invoices'));
        $this->assertTrue($user->hasPermission('data_program'));
        $this->assertFalse($user->hasPermission('form_program'));
    }

    public function test_can_fetch_active_telemarketing_contacts(): void
    {
        User::factory()->create([
            'name' => 'Siti Telemarketing',
            'role' => 'telemarketing',
            'whatsapp' => '6281234567890',
            'is_active' => true,
        ]);
        User::factory()->create([
            'name' => 'Inactive Telemarketing',
            'role' => 'telemarketing',
            'whatsapp' => '628111111111',
            'is_active' => false,
        ]);
        User::factory()->create([
            'name' => 'Budi SCM',
            'role' => 'scm',
            'whatsapp' => '628222222222',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/telemarketing-contacts');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'contacts')
            ->assertJsonPath('contacts.0.name', 'Siti Telemarketing')
            ->assertJsonPath('contacts.0.whatsapp', '6281234567890');
    }

    public function test_admin_can_assign_top_only_msi_only_or_both_companies_to_users(): void
    {
        $msiOnly = $this->postJson('/api/users', [
            'name' => 'Staff MSI',
            'email' => 'msi@scm.test',
            'whatsapp' => '081200000011',
            'role' => 'telemarketing',
            'company_slugs' => ['msi'],
            'password' => 'rahasia2026',
            'is_active' => true,
        ])->assertCreated();

        $msiOnly->assertJsonPath('data.company_slugs', ['msi'])
            ->assertJsonPath('data.accessible_companies', ['msi']);

        $msiUser = User::where('email', 'msi@scm.test')->firstOrFail();
        $this->assertFalse($msiUser->canAccessCompanySlug('top'));
        $this->assertTrue($msiUser->canAccessCompanySlug('msi'));

        // Telemarketing contacts are scoped to the active company
        User::factory()->create([
            'name' => 'Staff TOP',
            'role' => 'telemarketing',
            'whatsapp' => '628120000012',
            'company_slugs' => ['top'],
            'is_active' => true,
        ]);

        $this->getJson('/api/telemarketing-contacts', ['X-Company' => 'top'])
            ->assertOk()
            ->assertJsonCount(1, 'contacts')
            ->assertJsonPath('contacts.0.name', 'Staff TOP');

        $this->getJson('/api/telemarketing-contacts', ['X-Company' => 'msi'])
            ->assertOk()
            ->assertJsonCount(1, 'contacts')
            ->assertJsonPath('contacts.0.name', 'Staff MSI');

        // Updating to both companies grants access to both
        $this->putJson("/api/users/{$msiUser->id}", [
            'name' => 'Staff Dual',
            'email' => 'msi@scm.test',
            'role' => 'ar',
            'company_slugs' => ['top', 'msi'],
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.company_slugs', ['top', 'msi'])
            ->assertJsonPath('data.accessible_companies', ['top', 'msi']);

        // Reject empty company list
        $this->putJson("/api/users/{$msiUser->id}", [
            'name' => 'Staff Dual',
            'email' => 'msi@scm.test',
            'role' => 'ar',
            'company_slugs' => [],
            'is_active' => true,
        ])->assertStatus(422);
    }

    public function test_non_admin_user_manager_cannot_assign_or_touch_outside_companies(): void
    {
        $msiScm = User::factory()->create([
            'role' => 'scm',
            'company_slugs' => ['msi'],
            'permissions' => User::getDefaultPermissionsForRole('scm'),
            'is_active' => true,
        ]);
        $topUser = User::factory()->create(['role' => 'ar', 'company_slugs' => ['top']]);

        $this->actingAs($msiScm)
            ->postJson('/api/users', [
                'name' => 'Cross Company',
                'role' => 'ar',
                'company_slugs' => ['top'],
                'password' => 'rahasia2026',
            ], ['X-Company' => 'msi'])
            ->assertForbidden();

        $this->actingAs($msiScm)
            ->putJson("/api/users/{$topUser->id}", [
                'name' => 'Hijack',
                'role' => 'ar',
                'company_slugs' => ['msi'],
            ], ['X-Company' => 'msi'])
            ->assertForbidden();
    }
}
