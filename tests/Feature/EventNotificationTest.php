<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Notifications\EventUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_status_changes_notify_other_members_and_link_to_the_event(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();
        $event = Event::factory()->for($leader, 'creator')->create();
        $event->members()->attach($leader->getKey(), ['role' => 'leader', 'joined_at' => now()]);
        $event->members()->attach($member->getKey(), ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($leader)
            ->post(route('events.status', $event), ['status' => 'published'])
            ->assertRedirect(route('events.show', $event));

        $notification = $member->notifications()->firstOrFail();
        $this->assertSame(EventUpdate::class, $notification->type);
        $this->assertSame('event.status_changed', $notification->data['notification_kind']);
        $this->assertSame($event->getKey(), $notification->data['event_id']);
        $this->assertSame(0, $leader->notifications()->count());

        $this->actingAs($member)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Event status changed')
            ->assertSee(route('events.show', $event));
    }

    public function test_new_event_member_is_notified_unless_they_disabled_event_updates(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();
        $event = Event::factory()->for($leader, 'creator')->create();
        $event->members()->attach($leader->getKey(), ['role' => 'leader', 'joined_at' => now()]);

        $this->actingAs($leader)
            ->post(route('events.members.store', $event), ['user_id' => $member->getKey()])
            ->assertRedirect();

        $this->assertSame('event.member_added', $member->notifications()->firstOrFail()->data['notification_kind']);

        $memberWithoutUpdates = User::factory()->create();
        $memberWithoutUpdates->forceFill([
            'notification_preferences' => [
                'task_assignments' => true,
                'task_status_updates' => true,
                'deadline_reminders' => true,
                'event_updates' => false,
            ],
        ])->save();

        $this->post(route('events.members.store', $event), ['user_id' => $memberWithoutUpdates->getKey()])
            ->assertRedirect();

        $this->assertSame(0, $memberWithoutUpdates->notifications()->count());
    }
}
