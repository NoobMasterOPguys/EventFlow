<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEventManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_reschedule_an_event_to_the_past_and_can_archive_and_restore_it(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['system_role' => 'admin'])->save();
        $creator = User::factory()->create();
        $event = Event::factory()->for($creator, 'creator')->create();
        $originalStart = $event->start_at->format('Y-m-d H:i:s');

        $this->actingAs($admin)
            ->patch(route('admin.events.update', $event), [
                'title' => 'Changed event',
                'description' => 'Updated by an administrator.',
                'category' => 'Academic',
                'location' => 'Main Hall',
                'start_at' => now()->subHours(3)->format('Y-m-d\TH:i'),
                'end_at' => now()->subHour()->format('Y-m-d\TH:i'),
                'status' => 'published',
            ])
            ->assertSessionHasErrors(['start_at', 'end_at']);

        $this->assertSame($originalStart, $event->fresh()->start_at->format('Y-m-d H:i:s'));
        $this->assertSame($event->title, $event->fresh()->title);

        $this->delete(route('admin.events.destroy', $event))
            ->assertRedirect(route('admin.events.index'));

        $this->assertSoftDeleted($event);

        $this->post(route('admin.events.restore', $event))
            ->assertRedirect();

        $this->assertNull($event->fresh()->deleted_at);
    }
}
