<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProvisionAdministratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_administrator_is_created_with_a_confirmed_strong_password(): void
    {
        $this->artisan('admin:provision', ['email' => 'ADMIN@example.com'])
            ->expectsQuestion('Administrator name', 'Platform Administrator')
            ->expectsQuestion('Administrator password (minimum 12 characters with upper/lowercase, number, and symbol)', 'SecurePassword123!')
            ->expectsQuestion('Confirm administrator password', 'SecurePassword123!')
            ->expectsConfirmation('Create the initial administrator account?', 'yes')
            ->expectsOutputToContain('Initial administrator created.')
            ->assertExitCode(0);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->assertSame('admin', $admin->system_role);
        $this->assertTrue(Hash::check('SecurePassword123!', $admin->password));
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->getKey(),
            'action' => 'administrator.provisioned',
            'auditable_id' => $admin->getKey(),
        ]);
    }

    public function test_provisioning_refuses_to_create_a_second_administrator(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['system_role' => 'admin'])->save();

        $this->artisan('admin:provision', ['email' => 'second@example.com'])
            ->expectsOutputToContain('An administrator already exists.')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
    }
}
