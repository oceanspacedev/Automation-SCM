<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_registration_endpoint_is_disabled(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Calon User',
            'email' => 'calon@scm.test',
            'whatsapp' => '08123456789',
            'role' => 'ar',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $this->assertTrue(in_array($response->status(), [404, 405], true));
        $this->assertDatabaseMissing('users', ['email' => 'calon@scm.test']);
    }

    public function test_inactive_user_cannot_login(): void
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

    public function test_admin_can_activate_inactive_user_and_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'waiting@scm.test',
            'password' => bcrypt('password123'),
            'is_active' => false,
            'role' => 'scm',
        ]);

        $approveResponse = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patchJson("/api/users/{$user->id}/approve");

        $approveResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => true,
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'waiting@scm.test',
            'password' => 'password123',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('success', true);

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_new_password_needs_8_characters_with_letters_and_numbers(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $create = fn (string $password) => $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Akun Baru',
            'email' => 'baru@scm.test',
            'role' => 'scm',
            'password' => $password,
        ]);

        $create('abc123')->assertStatus(422)->assertJsonPath('errors.password.0', 'Password minimal 8 karakter.');
        $create('hanyahuruf')->assertStatus(422)->assertJsonPath('errors.password.0', 'Password harus berisi huruf dan angka.');
        $create('12345678')->assertStatus(422)->assertJsonPath('errors.password.0', 'Password harus berisi huruf dan angka.');
        $create('rahasia2026')->assertCreated();
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
