<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_regular_user_is_forbidden_from_admin_routes(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'system_role' => 'user',
        ]);
    }

    public function test_administrator_can_access_platform_dashboard_and_management_sections(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['system_role' => 'admin'])->save();

        $this->assertSame('active', $admin->fresh()->account_status);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $this->assertTrue($admin->fresh()->isAdministrator());
        $this->assertTrue($admin->fresh()->isActive());
        $response
            ->assertOk()
            ->assertViewIs('admin.dashboard');

        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.events.index'))->assertOk();
        $this->get(route('admin.tasks.index'))->assertOk();
        $this->get(route('admin.categories.index'))->assertOk();
        $this->get(route('admin.audit.index'))->assertOk();
    }

    public function test_suspended_account_is_logged_out_when_it_requests_a_protected_page(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'account_status' => 'suspended',
            'suspended_at' => now(),
        ])->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'This account has been suspended.');

        $this->assertGuest();
    }
}
