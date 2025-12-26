<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_their_profile(): void
    {
        $user = User::factory()->create([
            'identity_state' => 'verified',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'uuid',
                    'profile' => [
                        'first_name',
                        'last_name',
                        'full_name',
                    ],
                ],
            ]);
    }

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->create([
            'identity_state' => 'verified',
        ]);
        
        User::factory()->count(3)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/users');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'uuid',
                        'profile',
                        'identity',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->create([
            'identity_state' => 'verified',
        ]);

        Sanctum::actingAs($admin);

        $userData = [
            'national_id' => '1234567890',
            'national_id_country_code' => 'USA',
            'email' => 'newuser@example.com',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'data_residency_region' => 'US',
        ];

        $response = $this->postJson('/api/users', $userData);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'uuid',
                    'profile',
                ],
            ])
            ->assertJson(['message' => 'User created successfully']);
    }

    public function test_user_can_update_their_profile(): void
    {
        $user = User::factory()->create([
            'identity_state' => 'verified',
        ]);

        Sanctum::actingAs($user);

        $updateData = [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'display_name' => 'Updated Display',
        ];

        $response = $this->putJson("/api/users/{$user->id}", $updateData);

        $response->assertOk()
            ->assertJson(['message' => 'User updated successfully']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Updated',
            'last_name' => 'Name',
        ]);
    }

    public function test_admin_can_verify_user_identity(): void
    {
        $admin = User::factory()->create([
            'identity_state' => 'verified',
        ]);
        
        $user = User::factory()->create([
            'identity_state' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/users/{$user->id}/verify-identity", [
            'method' => 'passport',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'User identity verified successfully']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'identity_state' => 'verified',
            'identity_verification_method' => 'passport',
        ]);
    }

    public function test_user_can_update_their_password(): void
    {
        $user = User::factory()->create([
            'identity_state' => 'verified',
            'password_hash' => Hash::make('OldPassword123!'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/users/{$user->id}/update-password", [
            'current_password' => 'OldPassword123!',
            'new_password' => 'NewPassword123!',
            'new_password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Password updated successfully']);

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password_hash));
    }

    public function test_user_can_enable_mfa(): void
    {
        $user = User::factory()->create([
            'identity_state' => 'verified',
            'mfa_enabled' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/users/{$user->id}/enable-mfa");

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'secret',
                'qr_code_url',
            ]);

        $user->refresh();
        $this->assertTrue($user->mfa_enabled);
    }

    public function test_register_new_user(): void
    {
        $userData = [
            'national_id' => '9876543210',
            'national_id_country_code' => 'GBR',
            'email' => 'registertest@example.com',
            'first_name' => 'Register',
            'last_name' => 'Test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'data_residency_region' => 'EU',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'uuid',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email_hash' => hash('sha256', 'registertest@example.com'),
            'national_id_country_code' => 'GBR',
        ]);
    }

    public function test_login_successful(): void
    {
        $user = User::factory()->create([
            'email_hash' => hash('sha256', 'testlogin@example.com'),
            'password_hash' => Hash::make('Password123!'),
            'identity_state' => 'verified',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'testlogin@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user',
                    'token',
                    'requires_mfa',
                ],
            ]);
    }

    public function test_logout_successful(): void
    {
        $user = User::factory()->create([
            'identity_state' => 'verified',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Successfully logged out']);
    }

    public function test_user_cannot_access_protected_routes_without_auth(): void
    {
        $response = $this->getJson('/api/me');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/users');
        $response->assertUnauthorized();

        $response = $this->postJson('/api/logout');
        $response->assertUnauthorized();
    }
}