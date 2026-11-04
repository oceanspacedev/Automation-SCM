<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_only_reach_login_the_public_form_and_webhooks(): void
    {
        // Form publik & cek sesi tetap terbuka
        $this->getJson('/api/user')->assertOk()->assertJsonPath('user', null);
        $this->getJson('/api/brands')->assertOk();
        $this->getJson('/api/program-submissions/form-options', ['X-Area' => 'jabar'])->assertOk();
        $this->getJson('/api/dealers/suggestions?query=toko', ['X-Area' => 'jabar'])->assertOk();

        foreach (['/api/users', '/api/dealers', '/api/drafts', '/api/invoices', '/api/data-program', '/api/program-submissions', '/api/customers', '/api/dashboard'] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }

        $this->deleteJson('/api/dealers/1')->assertUnauthorized();
        $this->postJson('/api/users', [
            'name' => 'Penyusup',
            'email' => 'penyusup@example.com',
            'role' => 'admin',
            'password' => 'rahasia123',
        ])->assertUnauthorized();

        $this->assertDatabaseMissing('users', ['email' => 'penyusup@example.com']);
    }

    public function test_only_an_admin_can_create_or_change_admin_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $newUser = fn (string $role) => ['name' => "User {$role}", 'email' => "{$role}@example.com", 'role' => $role, 'password' => 'rahasia123'];

        // SCM dengan akses Master User boleh kelola user biasa, tapi tidak boleh membuat/mengubah Admin
        $this->actingAs(User::factory()->create(['role' => 'scm', 'is_active' => true]));
        $this->getJson('/api/users')->assertOk();
        $this->postJson('/api/users', $newUser('ar'))->assertCreated();
        $this->postJson('/api/users', $newUser('admin'))->assertForbidden();
        $this->putJson("/api/users/{$admin->id}", ['name' => 'Diambil alih', 'email' => $admin->email, 'role' => 'admin'])->assertForbidden();
        $this->patchJson("/api/users/{$admin->id}/toggle-status")->assertForbidden();

        // Tanpa akses Master User tidak bisa melihat user sama sekali
        $this->actingAs(User::factory()->create(['role' => 'telemarketing', 'is_active' => true]));
        $this->getJson('/api/users')->assertForbidden();

        $this->actingAs($admin);
        $this->postJson('/api/users', $newUser('admin'))->assertCreated();
    }

    public function test_every_signed_in_user_sees_the_dashboard_metrics(): void
    {
        $this->assertArrayNotHasKey('dashboard', User::AVAILABLE_PERMISSIONS);

        $this->actingAs(User::factory()->create(['role' => 'telemarketing', 'is_active' => true, 'permissions' => User::getDefaultPermissionsForRole('telemarketing')]));
        $this->getJson('/api/dashboard')->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'ar', 'is_active' => true, 'permissions' => ['drafts']]));
        $this->getJson('/api/dashboard')->assertOk();
    }

    public function test_dashboard_is_taken_out_of_saved_user_permissions(): void
    {
        $user = User::factory()->create(['permissions' => ['drafts', 'dashboard', 'invoices']]);

        (require database_path('migrations/2026_11_04_131535_remove_dashboard_from_user_permissions.php'))->up();

        $this->assertSame(['drafts', 'invoices'], $user->fresh()->permissions);
    }
}
