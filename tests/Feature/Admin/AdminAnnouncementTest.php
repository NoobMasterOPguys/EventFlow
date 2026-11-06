<?php

namespace Tests\Feature\Admin;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\SystemAnnouncement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_announcement_can_be_published_once_to_active_users(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['system_role' => 'admin'])->save();
        $recipient = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.notifications.store'), [
                'title' => 'Schedule update',
                'body' => 'The event calendar will be updated this evening.',
                'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Announcement scheduled.');

        $announcement = Announcement::query()->firstOrFail();
        $this->assertSame('scheduled', $announcement->status);

        $this->patch(route('admin.notifications.publish', $announcement))
            ->assertRedirect()
            ->assertSessionHas('success', 'Announcement published to active users.');

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->getKey(),
            'status' => 'published',
            'recipients_count' => 2,
        ]);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(1, $recipient->notifications()->where('type', SystemAnnouncement::class)->count());

        $this->patch(route('admin.notifications.publish', $announcement))
            ->assertRedirect()
            ->assertSessionHas('error', 'This announcement has already been published or cancelled.');

        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_cancelled_scheduled_announcement_is_not_published_by_the_scheduler(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['system_role' => 'admin'])->save();
        $announcement = Announcement::query()->create([
            'created_by' => $admin->getKey(),
            'title' => 'Cancelled update',
            'body' => 'This message should not be sent.',
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinute(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.notifications.cancel', $announcement))
            ->assertRedirect()
            ->assertSessionHas('success', 'Scheduled announcement cancelled.');

        $this->artisan('announcements:publish-scheduled')->assertSuccessful();

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->getKey(),
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseCount('notifications', 0);
    }
}
