<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_defaults_to_inactive_pending_acc(): void
    {
        $payload = [
            'name' => 'Calon User',
            'email' => 'calon@scm.test',
            'whatsapp' => '08123456789',
            'role' => 'ar',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Calon User')
            ->assertJsonPath('data.email', 'calon@scm.test')
            ->assertJsonPath('data.role', 'ar')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'email' => 'calon@scm.test',
            'role' => 'ar',
            'is_active' => false,
        ]);

        $user = User::where('email', 'calon@scm.test')->first();
        $this->assertFalse($user->is_active);
        // Default permissions for AR are assigned
        $this->assertTrue($user->hasPermission('invoices') || in_array('invoices', $user->permissions));
    }

    public function test_inactive_user_cannot_login_until_approved(): void
    {
        User::factory()->create([
            'email' => 'pending@scm.test',
            'password' => bcrypt('password123'),
            'is_active' => false,
            'role' => 'telemarketing',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'pending@scm.test',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonFragment([
                'message' => 'Akun Anda belum aktif atau sedang menunggu persetujuan (ACC) dari Admin.',
            ]);

        $this->assertGuest();
    }

    public function test_admin_can_approve_pending_user_and_then_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'waiting@scm.test',
            'password' => bcrypt('password123'),
            'is_active' => false,
            'role' => 'scm',
        ]);

        // Admin ACCs the user
        $approveResponse = $this->patchJson("/api/users/{$user->id}/approve");

        $approveResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => true,
        ]);

        // Now user can log in
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'waiting@scm.test',
            'password' => 'password123',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('success', true);

        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_validation_fails_on_duplicate_email_or_password_mismatch(): void
    {
        User::factory()->create(['email' => 'existing@scm.test']);

        // Duplicate email
        $res1 = $this->postJson('/api/register', [
            'name' => 'Duplikat',
            'email' => 'existing@scm.test',
            'role' => 'scm',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $res1->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Mismatched confirmation
        $res2 = $this->postJson('/api/register', [
            'name' => 'Salah Pass',
            'email' => 'salah@scm.test',
            'role' => 'scm',
            'password' => 'secret123',
            'password_confirmation' => 'berbeda456',
        ]);

        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_verify_wa_otp(): void
    {
        $user = User::factory()->create([
            'whatsapp' => '6281224290502',
            'is_active' => true,
        ]);

        Cache::put('wa_otp:6281224290502', [
            'otp' => '123456',
            'user_id' => $user->id,
            'attempts' => 0,
        ], now()->addMinutes(15));

        // Test with 08xxx input
        $res = $this->postJson('/api/auth/wa-otp/verify', [
            'whatsapp' => '081224290502',
            'otp' => '123456',
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true);

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_with_username(): void
    {
        $user = User::factory()->create([
            'name' => 'admin_gudang',
            'email' => 'gudang@scm.test',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $res = $this->postJson('/api/login', [
            'login' => 'admin_gudang',
            'password' => 'password123',
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true);

        $this->assertAuthenticatedAs($user);
    }
}
