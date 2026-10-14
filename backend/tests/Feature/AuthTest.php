<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPosData;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use CreatesPosData, RefreshDatabase;

    public function test_login_returns_token_and_user(): void
    {
        User::factory()->create(['email' => 'kasir@test.local']);

        $this->postJson('/api/login', [
            'email' => 'kasir@test.local',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'kasir')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email', 'role']]]);
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create(['email' => 'kasir@test.local']);

        $this->postJson('/api/login', [
            'email' => 'kasir@test.local',
            'password' => 'salah',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Email atau kata sandi salah.');
    }

    public function test_inactive_account_cannot_login(): void
    {
        User::factory()->inactive()->create(['email' => 'nonaktif@test.local']);

        $this->postJson('/api/login', [
            'email' => 'nonaktif@test.local',
            'password' => 'password',
        ])->assertForbidden();
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_logout_revokes_the_current_token(): void
    {
        User::factory()->create(['email' => 'kasir@test.local']);

        $token = $this->postJson('/api/login', [
            'email' => 'kasir@test.local',
            'password' => 'password',
        ])->json('data.token');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_password_change_requires_current_password(): void
    {
        User::factory()->create(['email' => 'kasir@test.local']);

        $token = $this->postJson('/api/login', [
            'email' => 'kasir@test.local',
            'password' => 'password',
        ])->json('data.token');

        $this->withToken($token)->putJson('/api/password', [
            'current_password' => 'salah',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertUnprocessable();

        $this->withToken($token)->putJson('/api/password', [
            'current_password' => 'password',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertOk();

        $this->postJson('/api/login', [
            'email' => 'kasir@test.local',
            'password' => 'rahasia123',
        ])->assertOk();

        $this->postJson('/api/login', [
            'email' => 'kasir@test.local',
            'password' => 'password',
        ])->assertUnauthorized();
    }
}