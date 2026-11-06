<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskDeadlineReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeadlineReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sends_all_three_reminder_stages_once_to_the_assignee(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
        $assignee = User::factory()->create();
        $otherUser = User::factory()->create();
        $event = $this->createEventWithLeader($assignee);
        $this->createTask($event, $assignee, $assignee, ['title' => 'Set the event schedule', 'due_at' => now()->addHours(24)]);
        $this->createTask($event, $assignee, $assignee, ['title' => 'Print name cards', 'due_at' => now()->addHour()]);
        $this->createTask($event, $assignee, $assignee, ['title' => 'Open the event doors', 'due_at' => now()]);

        $this->artisan('tasks:send-deadline-notifications')->assertSuccessful();
        $this->artisan('tasks:send-deadline-notifications')->assertSuccessful();

        $notifications = $assignee->notifications()->where('type', TaskDeadlineReminder::class)->get();
        $stages = $notifications->pluck('data.stage')->sort()->values()->all();

        $this->assertSame(['1_hour', '24_hours', 'deadline'], $stages);
        $this->assertCount(3, $notifications);
        $this->assertCount(0, $otherUser->notifications);
        $this->assertSame('Deadline reached', $notifications->firstWhere('data.stage', 'deadline')->data['deadline_label']);
    }

    public function test_completed_and_deleted_tasks_do_not_receive_reminders(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
        $leader = User::factory()->create();
        $event = $this->createEventWithLeader($leader);
        $this->createTask($event, $leader, $leader, [
            'title' => 'Completed work',
            'status' => 'completed',
            'due_at' => now()->addHours(24),
        ]);
        $deletedTask = $this->createTask($event, $leader, $leader, [
            'title' => 'Deleted work',
            'due_at' => now()->addHour(),
        ]);
        $deletedTask->delete();
        $this->createTask($event, $leader, $leader, [
            'title' => 'Active work',
            'due_at' => now()->addHour(),
        ]);

        $this->artisan('tasks:send-deadline-notifications')->assertSuccessful();

        $this->assertCount(1, $leader->notifications()->where('type', TaskDeadlineReminder::class)->get());
    }

    public function test_changed_deadline_uses_a_new_reminder_identifier_without_duplicate_repeats(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
        $leader = User::factory()->create();
        $event = $this->createEventWithLeader($leader);
        $task = $this->createTask($event, $leader, $leader, [
            'title' => 'Submit the final plan',
            'due_at' => now()->addHours(24),
        ]);

        $this->artisan('tasks:send-deadline-notifications')->assertSuccessful();
        $task->update(['due_at' => now()->addHours(24)->subMinute()]);
        $this->artisan('tasks:send-deadline-notifications')->assertSuccessful();
        $this->artisan('tasks:send-deadline-notifications')->assertSuccessful();

        $notifications = $leader->notifications()->where('type', TaskDeadlineReminder::class)->get();

        $this->assertCount(2, $notifications);
        $this->assertNotSame($notifications[0]->id, $notifications[1]->id);
    }

    public function test_assignee_can_disable_deadline_reminders(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
        $leader = User::factory()->create();
        $leader->forceFill([
            'notification_preferences' => [
                'task_assignments' => true,
                'task_status_updates' => true,
                'deadline_reminders' => false,
                'event_updates' => true,
            ],
        ])->save();
        $event = $this->createEventWithLeader($leader);
        $this->createTask($event, $leader, $leader, ['due_at' => now()->addHour()]);

        $this->artisan('tasks:send-deadline-notifications')->assertSuccessful();

        $this->assertSame(0, $leader->notifications()->count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createEventWithLeader(User $leader, array $attributes = []): Event
    {
        $event = Event::factory()->for($leader, 'creator')->create($attributes);
        $event->members()->attach($leader->getKey(), ['role' => 'leader', 'joined_at' => now()]);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTask(Event $event, User $creator, User $assignee, array $attributes = []): Task
    {
        $task = $event->tasks()->make(array_replace([
            'title' => 'Prepare the event',
            'description' => null,
            'due_at' => now()->addHours(24),
            'priority' => 'medium',
            'status' => 'not_started',
        ], $attributes));
        $task->creator()->associate($creator);
        $task->assignee()->associate($assignee);
        $task->save();

        return $task;
    }
}
