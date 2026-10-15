<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPosData;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use CreatesPosData, RefreshDatabase;

    public function test_admin_can_create_a_cashier_who_can_then_log_in(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/users', [
            'name' => 'Kasir Baru',
            'email' => 'baru@test.local',
            'password' => 'password123',
            'role' => 'kasir',
        ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'kasir')
            ->assertJsonPath('data.is_active', true);

        $this->postJson('/api/login', [
            'email' => 'baru@test.local',
            'password' => 'password123',
        ])->assertOk();
    }

    public function test_email_must_be_unique(): void
    {
        $this->actingAsAdmin();
        $existing = $this->cashier();

        $this->postJson('/api/users', [
            'name' => 'Duplikat',
            'email' => $existing->email,
            'password' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_deactivating_an_account_revokes_tokens_and_blocks_login(): void
    {
        $this->actingAsAdmin();
        $cashier = $this->cashier();
        $cashier->createToken('hp');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->putJson("/api/users/{$cashier->id}", ['is_active' => false])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->postJson('/api/login', [
            'email' => $cashier->email,
            'password' => 'password',
        ])->assertForbidden();
    }

    public function test_admin_cannot_deactivate_or_demote_themselves(): void
    {
        $admin = $this->actingAsAdmin();

        $this->putJson("/api/users/{$admin->id}", ['is_active' => false])->assertUnprocessable();
        $this->putJson("/api/users/{$admin->id}", ['role' => 'kasir'])->assertUnprocessable();

        $fresh = $admin->fresh();

        $this->assertSame('admin', $fresh->role);
        $this->assertTrue((bool) $fresh->is_active);
    }

    public function test_password_reset_by_admin_revokes_tokens_and_applies_the_new_password(): void
    {
        $this->actingAsAdmin();
        $cashier = $this->cashier();
        $cashier->createToken('hp');

        $this->putJson("/api/users/{$cashier->id}", ['password' => 'baru12345'])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->postJson('/api/login', [
            'email' => $cashier->email,
            'password' => 'baru12345',
        ])->assertOk();
    }

    public function test_blank_password_on_update_keeps_the_existing_password(): void
    {
        $this->actingAsAdmin();
        $cashier = $this->cashier();

        $this->putJson("/api/users/{$cashier->id}", [
            'name' => 'Nama Baru',
            'password' => '',
        ])->assertOk();

        $this->postJson('/api/login', [
            'email' => $cashier->email,
            'password' => 'password',
        ])->assertOk();
    }
}