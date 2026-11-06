<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_permissions_are_enforced_by_the_server(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill([
            'system_role' => 'admin',
            'admin_permissions' => ['dashboard.view', 'users.view'],
        ])->save();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk();

        $this->get(route('admin.settings.index'))->assertForbidden();
        $this->get(route('admin.reports.index'))->assertForbidden();
    }

    public function test_regular_user_cannot_access_an_admin_permission_route(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.reports.index'))
            ->assertForbidden();
    }
}
