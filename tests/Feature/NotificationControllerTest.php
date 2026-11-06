<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Notifications\EventScheduleReminder;
use App\Notifications\TaskDeadlineReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_feed_returns_only_the_authenticated_users_unread_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $event = Event::factory()->for($user, 'creator')->create([
            'status' => 'published',
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(2),
        ]);
        $event->members()->attach($user->getKey(), ['role' => 'leader', 'joined_at' => now()]);

        $user->notify(new EventScheduleReminder($event, '1_hour'));
        $otherUser->notify(new EventScheduleReminder($event, '1_hour'));

        $this->actingAs($user)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.title', 'Event starts in 1 hour')
            ->assertJsonPath('notifications.0.message', $event->title)
            ->assertJsonPath('notifications.0.url', route('events.show', $event));
    }

    public function test_user_can_view_and_mark_their_notification_as_read(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'creator')->create();
        $event->members()->attach($user->getKey(), ['role' => 'leader', 'joined_at' => now()]);
        $task = $event->tasks()->make([
            'title' => 'Prepare the invitations',
            'due_at' => now()->addHour(),
            'priority' => 'high',
            'status' => 'not_started',
        ]);
        $task->creator()->associate($user);
        $task->assignee()->associate($user);
        $task->save();
        $user->notify(new TaskDeadlineReminder($task, '1_hour'));
        $notification = $user->notifications()->firstOrFail();

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Due within 1 hour')
            ->assertSee('Prepare the invitations')
            ->assertSee(route('tasks.edit', $task));

        $this->patch(route('notifications.read', $notification->getKey()))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_access_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $event = Event::factory()->for($owner, 'creator')->create();
        $event->members()->attach($owner->getKey(), ['role' => 'leader', 'joined_at' => now()]);
        $task = $event->tasks()->make([
            'title' => 'Private task reminder',
            'due_at' => now()->addHour(),
            'priority' => 'medium',
            'status' => 'not_started',
        ]);
        $task->creator()->associate($owner);
        $task->assignee()->associate($owner);
        $task->save();
        $owner->notify(new TaskDeadlineReminder($task, '1_hour'));
        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs($otherUser)
            ->patch(route('notifications.read', $notification->getKey()))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'creator')->create();
        $event->members()->attach($user->getKey(), ['role' => 'leader', 'joined_at' => now()]);

        foreach (['First reminder', 'Second reminder'] as $title) {
            $task = $event->tasks()->make([
                'title' => $title,
                'due_at' => now()->addHour(),
                'priority' => 'low',
                'status' => 'not_started',
            ]);
            $task->creator()->associate($user);
            $task->assignee()->associate($user);
            $task->save();
            $user->notify(new TaskDeadlineReminder($task, '1_hour'));
        }

        $this->actingAs($user)
            ->patch(route('notifications.read-all'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, $user->unreadNotifications()->count());
    }
}
