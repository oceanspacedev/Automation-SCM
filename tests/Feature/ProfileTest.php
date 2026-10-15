<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_change_a_profile(): void
    {
        $this->putJson('/api/profile', ['name' => 'X', 'email' => 'x@example.com'])->assertUnauthorized();
        $this->postJson('/api/profile/photo')->assertUnauthorized();
        $this->putJson('/api/profile/password')->assertUnauthorized();
    }

    public function test_user_can_update_name_email_and_whatsapp(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['email' => 'dipakai@example.com']);

        $this->actingAs($user)
            ->putJson('/api/profile', ['name' => 'Reza', 'email' => 'dipakai@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->actingAs($user)
            ->putJson('/api/profile', ['name' => 'Reza', 'email' => 'reza@example.com', 'whatsapp' => '0812-2429-0502'])
            ->assertOk()
            ->assertJsonPath('user.whatsapp', '6281224290502');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Reza', 'email' => 'reza@example.com']);
    }

    public function test_user_can_replace_and_remove_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $first = $this->actingAs($user)
            ->post('/api/profile/photo', ['photo' => UploadedFile::fake()->image('foto.jpg')], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('user.avatar_url');
        $firstPath = $user->fresh()->avatar_path;
        Storage::disk('public')->assertExists($firstPath);
        $this->assertStringEndsWith($firstPath, $first);

        // Foto baru menggantikan foto lama
        $this->actingAs($user)
            ->post('/api/profile/photo', ['photo' => UploadedFile::fake()->image('baru.png')], ['Accept' => 'application/json'])
            ->assertOk();
        Storage::disk('public')->assertMissing($firstPath);

        $this->actingAs($user)
            ->post('/api/profile/photo', ['photo' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);

        $secondPath = $user->fresh()->avatar_path;
        $this->actingAs($user)->deleteJson('/api/profile/photo')->assertOk()->assertJsonPath('user.avatar_url', null);
        Storage::disk('public')->assertMissing($secondPath);
    }

    public function test_password_change_needs_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'rahasia-lama']);

        $this->actingAs($user)
            ->putJson('/api/profile/password', ['current_password' => 'salah', 'password' => 'rahasia-baru', 'password_confirmation' => 'rahasia-baru'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        $this->actingAs($user)
            ->putJson('/api/profile/password', ['current_password' => 'rahasia-lama', 'password' => 'rahasia-baru', 'password_confirmation' => 'rahasia-baru'])
            ->assertOk();

        $this->assertTrue(Hash::check('rahasia-baru', $user->fresh()->password));
    }
}
