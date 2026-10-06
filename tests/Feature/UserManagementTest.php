<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_users_with_stats(): void
    {
        User::factory()->create(['role' => 'admin', 'is_active' => true]);
        User::factory()->create(['role' => 'scm', 'is_active' => true]);
        User::factory()->create(['role' => 'ar', 'is_active' => true]);
        User::factory()->create(['role' => 'telemarketing', 'is_active' => false]);

        $response = $this->getJson('/api/users');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stats.total', 4)
            ->assertJsonPath('stats.admin', 1)
            ->assertJsonPath('stats.scm', 1)
            ->assertJsonPath('stats.ar', 1)
            ->assertJsonPath('stats.telemarketing', 1)
            ->assertJsonPath('stats.active', 3)
            ->assertJsonPath('stats.inactive', 1);
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
}
