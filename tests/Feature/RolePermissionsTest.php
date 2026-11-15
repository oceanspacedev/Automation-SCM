<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_change_role_permissions(): void
    {
        $this->putJson('/api/role-permissions', ['role' => 'ar', 'permissions' => []])->assertUnauthorized();
        $this->assertNull(AppSetting::read('role_permissions_ar'));
    }

    public function test_admin_can_set_permissions_once_for_existing_and_future_role_members(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $first = User::factory()->create(['role' => 'ar', 'is_active' => true, 'permissions' => ['invoices']]);
        $second = User::factory()->create(['role' => 'ar', 'is_active' => true, 'permissions' => []]);
        $this->actingAs($admin)->putJson('/api/role-permissions', ['role' => 'ar', 'permissions' => ['drafts', 'drafts_edit']])->assertOk();
        $future = User::factory()->create(['role' => 'ar', 'is_active' => true]);
        foreach ([$first, $second, $future] as $user) {
            $this->assertTrue($user->canPerform('drafts', 'edit'));
            $this->assertFalse($user->canPerform('drafts', 'delete'));
            $this->assertFalse($user->hasPermission('invoices'));
            $this->assertSame(['drafts', 'drafts_edit'], $user->toArray()['effective_permissions']);
            $this->actingAs($user)->getJson('/api/drafts')->assertOk();
            $this->deleteJson('/api/drafts/999')->assertForbidden();
        }
    }

    public function test_non_admin_cannot_change_role_permissions_even_with_master_user_access(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'scm', 'is_active' => true, 'permissions' => ['master_user']]))
            ->putJson('/api/role-permissions', ['role' => 'scm', 'permissions' => ['drafts']])->assertForbidden();
        $this->assertNull(AppSetting::read('role_permissions_scm'));
    }

    public function test_role_permissions_validate_modules_and_preserve_admin_management_access(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        foreach ([['role' => 'unknown', 'permissions' => []], ['role' => 'ar', 'permissions' => ['unknown']], ['role' => 'ar', 'permissions' => ['drafts_delete']], ['role' => 'admin', 'permissions' => []]] as $payload) {
            $this->putJson('/api/role-permissions', $payload)->assertUnprocessable();
        }
        $this->putJson('/api/role-permissions', ['role' => 'ar', 'permissions' => []])->assertOk();
        $user = User::factory()->create(['role' => 'ar', 'is_active' => true, 'permissions' => ['drafts']]);
        $this->actingAs($user)->getJson('/api/drafts')->assertForbidden();
    }
}
