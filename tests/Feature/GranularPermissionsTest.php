<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GranularPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_create_edit_and_delete_are_independent(): void
    {
        $user = User::factory()->create(['role' => 'ar', 'is_active' => true]);
        $this->actingAs($user);
        AppSetting::write('role_permissions_ar', json_encode(['master_program', 'master_program_create']));
        $id = $this->postJson('/api/regions', ['name' => 'CREATE ONLY'])->assertCreated()->json('data.id');
        $this->putJson('/api/regions/'.$id, ['name' => 'DENIED'])->assertForbidden();
        $this->deleteJson('/api/regions/'.$id)->assertForbidden();
        $this->assertDatabaseHas('regions', ['id' => $id, 'name' => 'CREATE ONLY']);

        AppSetting::write('role_permissions_ar', json_encode(['master_program', 'master_program_edit']));
        $this->postJson('/api/regions', ['name' => 'DENIED'])->assertForbidden();
        $this->putJson('/api/regions/'.$id, ['name' => 'EDIT ONLY'])->assertOk();
        $this->assertDatabaseHas('regions', ['id' => $id, 'name' => 'EDIT ONLY']);
        $this->deleteJson('/api/regions/'.$id)->assertForbidden();

        AppSetting::write('role_permissions_ar', json_encode(['master_program', 'master_program_delete']));
        $this->putJson('/api/regions/'.$id, ['name' => 'DENIED'])->assertForbidden();
        $this->deleteJson('/api/regions/'.$id)->assertOk();
        $this->assertDatabaseMissing('regions', ['id' => $id]);
    }

    public function test_view_only_cannot_write_to_any_master_module(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'scm', 'is_active' => true, 'permissions' => ['master_program', 'master_dealer', 'master_customer', 'master_user']]));
        foreach (['brands', 'programs', 'regions', 'ar-contacts', 'team-contacts', 'dealers', 'customers', 'users'] as $endpoint) {
            $this->postJson('/api/'.$endpoint, [])->assertForbidden();
            $this->putJson('/api/'.$endpoint.'/999', [])->assertForbidden();
            $this->deleteJson('/api/'.$endpoint.'/999')->assertForbidden();
        }
    }

    public function test_edit_permission_does_not_allow_creating_drafts_or_generating_invoices(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'scm', 'is_active' => true, 'permissions' => ['drafts', 'drafts_edit', 'invoices', 'invoices_edit']]));
        $this->postJson('/api/drafts', [])->assertForbidden();
        $this->postJson('/api/invoices/generate/999', [])->assertForbidden();
        $this->postJson('/api/drafts/import', [])->assertForbidden();
        $this->assertDatabaseCount('drafts', 0);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_role_settings_accept_separate_create_permissions_and_require_view(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->putJson('/api/role-permissions', ['role' => 'ar', 'permissions' => ['master_dealer_create']])->assertUnprocessable();
        $this->putJson('/api/role-permissions', ['role' => 'ar', 'permissions' => ['master_dealer', 'master_dealer_create']])->assertOk();
        $this->assertSame(['master_dealer', 'master_dealer_create'], User::getDefaultPermissionsForRole('ar'));
    }
}
