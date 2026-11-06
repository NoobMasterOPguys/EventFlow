<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_task_assigned_to_current_user_appears_in_my_tasks(): void
    {
        $leader = User::factory()->create();
        $event = $this->createEventWithLeader($leader);

        $this->actingAs($leader)
            ->post(route('events.tasks.store', $event), $this->taskInput([
                'title' => 'Set up the welcome table',
                'assigned_to' => $leader->getKey(),
            ]))
            ->assertRedirect(route('events.show', $event));

        $this->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('Set up the welcome table')
            ->assertSee($event->title);
    }

    public function test_assigned_member_sees_a_task_but_an_unrelated_user_does_not(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();
        $unrelatedUser = User::factory()->create();
        $event = $this->createEventWithLeader($leader);
        $event->members()->attach($member->getKey(), ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($leader)
            ->post(route('events.tasks.store', $event), $this->taskInput([
                'title' => 'Prepare the presentation',
                'assigned_to' => $member->getKey(),
            ]))
            ->assertRedirect(route('events.show', $event));

        $this->actingAs($member)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('Prepare the presentation');

        $this->actingAs($unrelatedUser)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertDontSee('Prepare the presentation');
    }

    public function test_unassigned_and_soft_deleted_tasks_are_not_shown_in_my_tasks(): void
    {
        $leader = User::factory()->create();
        $event = $this->createEventWithLeader($leader);
        $unassignedTask = $this->createTask($event, $leader, null, ['title' => 'Unassigned task']);
        $deletedTask = $this->createTask($event, $leader, $leader, ['title' => 'Archived task']);
        $deletedTask->delete();

        $this->actingAs($leader)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertDontSee('Unassigned task')
            ->assertDontSee('Archived task');

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSee($unassignedTask->title)
            ->assertDontSee($deletedTask->title);
    }

    public function test_task_assignment_and_status_notifications_follow_assignee_preferences(): void
    {
        $leader = User::factory()->create();
        $assignee = User::factory()->create();
        $assignee->forceFill([
            'notification_preferences' => [
                'task_assignments' => false,
                'task_status_updates' => true,
                'deadline_reminders' => true,
                'event_updates' => true,
            ],
        ])->save();
        $event = $this->createEventWithLeader($leader);
        $event->members()->attach($assignee->getKey(), ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($leader)
            ->post(route('events.tasks.store', $event), $this->taskInput([
                'title' => 'Prepare the welcome materials',
                'assigned_to' => $assignee->getKey(),
            ]))
            ->assertRedirect(route('events.show', $event));

        $task = Task::query()->where('title', 'Prepare the welcome materials')->firstOrFail();
        $this->assertSame(0, $assignee->notifications()->where('type', TaskUpdate::class)->count());

        $this->patch(route('tasks.status', $task), ['status' => 'completed'])
            ->assertRedirect();

        $this->assertSame('status_changed', $assignee->notifications()->firstOrFail()->data['notification_kind']);
    }

    public function test_task_status_and_overdue_filters_query_the_assigned_tasks(): void
    {
        $leader = User::factory()->create();
        $event = $this->createEventWithLeader($leader);
        $this->createTask($event, $leader, $leader, ['title' => 'Completed work', 'status' => 'completed']);
        $this->createTask($event, $leader, $leader, ['title' => 'Late work', 'due_at' => now()->subMinute()]);
        $this->createTask($event, $leader, $leader, ['title' => 'Future work', 'due_at' => now()->addDay()]);

        $this->actingAs($leader)
            ->get(route('tasks.index', ['filter' => 'completed']))
            ->assertOk()
            ->assertSee('Completed work')
            ->assertDontSee('Late work')
            ->assertDontSee('Future work');

        $this->get(route('tasks.index', ['filter' => 'overdue']))
            ->assertOk()
            ->assertSee('Late work')
            ->assertDontSee('Completed work')
            ->assertDontSee('Future work');
    }

    public function test_new_or_changed_deadline_must_be_later_than_server_time(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
        $leader = User::factory()->create();
        $event = $this->createEventWithLeader($leader);

        $this->actingAs($leader)
            ->from(route('events.show', $event))
            ->post(route('events.tasks.store', $event), $this->taskInput([
                'title' => 'Past deadline task',
                'assigned_to' => $leader->getKey(),
                'due_at' => now()->subMinute()->format('Y-m-d\TH:i'),
            ]))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasErrors([
                'due_at' => 'Your deadline must be later than the current time. Please select a future date and time.',
            ]);

        $this->assertDatabaseMissing('tasks', ['title' => 'Past deadline task']);

        $task = $this->createTask($event, $leader, $leader, [
            'title' => 'Historical task',
            'due_at' => now()->subDay(),
        ]);

        $this->patch(route('tasks.update', $task), [
            ...$this->taskInput(),
            'title' => 'Changed historical task',
            'due_at' => now()->subHours(2)->format('Y-m-d\TH:i'),
        ])
            ->assertSessionHasErrors([
                'due_at' => 'Your deadline must be later than the current time. Please select a future date and time.',
            ]);

        $this->assertSame('Historical task', $task->fresh()->title);
    }

    public function test_same_day_deadline_compares_the_selected_time(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 5)->setTime(15, 30));
        $leader = User::factory()->create();
        $event = $this->createEventWithLeader($leader);

        $this->actingAs($leader)
            ->from(route('events.show', $event))
            ->post(route('events.tasks.store', $event), $this->taskInput([
                'title' => 'Past time today',
                'assigned_to' => $leader->getKey(),
                'due_at' => '2026-11-05T14:00',
            ]))
            ->assertSessionHasErrors([
                'due_at' => 'Your deadline must be later than the current time. Please select a future date and time.',
            ]);

        $this->post(route('events.tasks.store', $event), $this->taskInput([
            'title' => 'Future time today',
            'assigned_to' => $leader->getKey(),
            'due_at' => '2026-11-05T16:00',
        ]))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tasks', ['title' => 'Future time today']);
        $this->assertDatabaseMissing('tasks', ['title' => 'Past time today']);
    }

    public function test_unchanged_historical_deadline_does_not_block_other_task_edits(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
        $leader = User::factory()->create();
        $event = $this->createEventWithLeader($leader);
        $historicalDeadline = now()->subDay()->setSecond(45);
        $task = $this->createTask($event, $leader, $leader, [
            'title' => 'Old task title',
            'due_at' => $historicalDeadline,
        ]);

        $this->actingAs($leader)
            ->patch(route('tasks.update', $task), [
                ...$this->taskInput(),
                'title' => 'Updated historical task',
                'due_at' => $historicalDeadline->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasNoErrors();

        $this->assertSame('Updated historical task', $task->fresh()->title);
        $this->assertSame($historicalDeadline->format('Y-m-d H:i:s'), $task->fresh()->due_at->format('Y-m-d H:i:s'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createEventWithLeader(User $leader, array $overrides = []): Event
    {
        $event = Event::factory()->for($leader, 'creator')->create($overrides);
        $event->members()->attach($leader->getKey(), ['role' => 'leader', 'joined_at' => now()]);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function taskInput(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Coordinate the schedule',
            'description' => 'Confirm the event timeline.',
            'assigned_to' => '',
            'due_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'priority' => 'medium',
            'status' => 'not_started',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTask(Event $event, User $creator, ?User $assignee, array $attributes = []): Task
    {
        $task = $event->tasks()->make(array_replace([
            'title' => 'Prepare event materials',
            'description' => null,
            'due_at' => now()->addDay(),
            'priority' => 'medium',
            'status' => 'not_started',
        ], $attributes));
        $task->creator()->associate($creator);
        $task->assignee()->associate($assignee);
        $task->save();

        return $task;
    }
}
