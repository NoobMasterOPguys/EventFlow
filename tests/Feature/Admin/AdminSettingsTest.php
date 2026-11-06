<?php

namespace Tests\Feature\Admin;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_update_safe_settings_and_disable_public_registration(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['system_role' => 'admin'])->save();

        $this->actingAs($admin)
            ->patch(route('admin.settings.update'), [
                'application_name' => 'EventFlow Campus',
                'timezone' => 'Asia/Jakarta',
                'registration_enabled' => '0',
                'deadline_reminder_hours' => '36',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'System settings updated.');

        $this->assertSame('EventFlow Campus', SystemSetting::get('application_name'));
        $this->assertSame('Asia/Jakarta', SystemSetting::get('timezone'));
        $this->assertSame(false, SystemSetting::get('registration_enabled'));
        $this->assertSame(36, SystemSetting::get('deadline_reminder_hours'));
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->get(route('register'))->assertNotFound();
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->getKey(),
            'action' => 'setting.updated',
        ]);
    }

    public function test_invalid_timezone_and_reminder_values_are_rejected_without_writing_settings(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['system_role' => 'admin'])->save();

        $this->actingAs($admin)
            ->from(route('admin.settings.index'))
            ->patch(route('admin.settings.update'), [
                'application_name' => 'EventFlow',
                'timezone' => 'Not/A_Timezone',
                'registration_enabled' => '1',
                'deadline_reminder_hours' => '0',
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHasErrors(['timezone', 'deadline_reminder_hours']);

        $this->assertSame(null, SystemSetting::get('timezone'));
        $this->assertSame(null, SystemSetting::get('deadline_reminder_hours'));
    }
}
