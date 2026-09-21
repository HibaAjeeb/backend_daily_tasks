<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_use_the_returned_access_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Amina',
            'email' => 'amina@example.com',
            'password' => 'StrongPass123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['userId', 'name', 'email', 'accessToken', 'refreshToken', 'expiresIn']]);

        $this->withToken($response->json('data.accessToken'))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'amina@example.com');
    }

    public function test_user_can_login_refresh_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'StrongPass123']);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPass123',
        ])->assertOk();

        $accessToken = $login->json('data.accessToken');
        $refreshToken = $login->json('data.refreshToken');
        $this->postJson('/api/v1/auth/refresh', ['refreshToken' => $refreshToken])
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'token' => hash('sha256', explode('|', $refreshToken, 2)[1]),
        ]);

        $this->withToken($accessToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'token' => hash('sha256', explode('|', $accessToken, 2)[1]),
        ]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'WrongPass123',
        ])->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_protected_routes_require_an_access_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_expired_access_token_returns_token_expired(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('access', ['*'], now()->subMinute());

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'TOKEN_EXPIRED');
    }

    public function test_invalid_access_token_returns_unauthorized(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('access', ['*'], now()->addHour());

        $this->withToken($token->plainTextToken.'tampered')
            ->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_registration_enforces_the_password_policy(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Weak',
            'email' => 'weak@example.com',
            'password' => 'weakpass',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
}
