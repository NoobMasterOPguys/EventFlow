<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_a_standard_user_and_send_a_password_setup_link(): void
    {
        $admin = $this->createAdministrator();
        Notification::fake();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Casey Morgan',
                'email' => 'CASEY@example.com',
                'system_role' => 'admin',
            ])
            ->assertRedirect();

        $user = User::query()->where('email', 'casey@example.com')->firstOrFail();
        $this->assertSame('user', $user->system_role);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->getKey(),
            'action' => 'user.created',
            'auditable_id' => $user->getKey(),
        ]);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_administrator_can_suspend_and_reactivate_a_user(): void
    {
        $admin = $this->createAdministrator();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $user), [
                'account_status' => 'suspended',
                'suspension_reason' => 'Repeated abuse of event messaging.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Account suspended and active sessions invalidated.');

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'account_status' => 'suspended',
            'suspension_reason' => 'Repeated abuse of event messaging.',
            'suspended_by' => $admin->getKey(),
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->getKey(),
            'action' => 'user.status_changed',
            'auditable_id' => $user->getKey(),
        ]);

        $this->patch(route('admin.users.status', $user), ['account_status' => 'active'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Account reactivated.');

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'account_status' => 'active',
            'suspended_at' => null,
        ]);
    }

    public function test_administrator_cannot_demote_suspend_or_delete_the_last_active_administrator(): void
    {
        $admin = $this->createAdministrator();

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $admin), ['system_role' => 'user'])
            ->assertRedirect()
            ->assertSessionHas('error', 'You cannot change your own administrator role.');

        $this->patch(route('admin.users.status', $admin), ['account_status' => 'suspended'])
            ->assertSessionHasErrors('suspension_reason');

        $this->patch(route('admin.users.status', $admin), [
            'account_status' => 'suspended',
            'suspension_reason' => 'Cannot suspend the current operator.',
        ])
            ->assertRedirect()
            ->assertSessionHas('error', 'You cannot suspend your own account.');

        $otherAdmin = $this->createAdministrator('other-admin@example.com');
        $this->patch(route('admin.users.role', $otherAdmin), ['system_role' => 'user'])
            ->assertRedirect()
            ->assertSessionHas('success', 'System role updated.');

        $this->assertSame('user', $otherAdmin->fresh()->system_role);

        $this->delete(route('admin.users.destroy', $admin))
            ->assertRedirect()
            ->assertSessionHas('error', 'You cannot delete your own account from the administrator panel.');

        $this->assertSame('admin', $admin->fresh()->system_role);
    }

    public function test_regular_user_cannot_change_system_role_through_registration_or_admin_endpoint(): void
    {
        $admin = $this->createAdministrator();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('admin.users.role', $admin), ['system_role' => 'user'])
            ->assertForbidden();

        $this->assertSame('admin', $admin->fresh()->system_role);
    }

    public function test_admin_cannot_delete_a_user_with_existing_event_records(): void
    {
        $admin = $this->createAdministrator();
        $user = User::factory()->create();
        Event::factory()->for($user, 'creator')->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect()
            ->assertSessionHas('error', 'This account has linked events or tasks and cannot be safely deleted.');

        $this->assertModelExists($user);
    }

    public function test_admin_user_listing_searches_and_reports_database_counts(): void
    {
        $admin = $this->createAdministrator();
        $user = User::factory()->create(['name' => 'Taylor Reed', 'email' => 'taylor@example.com']);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'taylor@example.com']))
            ->assertOk()
            ->assertSee('Taylor Reed')
            ->assertSee('taylor@example.com');
    }

    private function createAdministrator(string $email = 'admin@example.com'): User
    {
        $admin = User::factory()->create(['email' => $email]);
        $admin->forceFill(['system_role' => 'admin'])->save();

        return $admin;
    }
}
