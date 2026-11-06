<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Notifications\TaskUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTaskManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_task_edits_validate_deadlines_notify_by_preference_and_support_restore(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['system_role' => 'admin'])->save();
        $leader = User::factory()->create();
        $assignee = User::factory()->create();
        $assignee->forceFill([
            'notification_preferences' => [
                'task_assignments' => false,
                'task_status_updates' => false,
                'deadline_reminders' => true,
                'event_updates' => true,
            ],
        ])->save();
        $event = Event::factory()->for($leader, 'creator')->create();
        $event->members()->attach($leader->getKey(), ['role' => 'leader', 'joined_at' => now()]);
        $event->members()->attach($assignee->getKey(), ['role' => 'member', 'joined_at' => now()]);
        $task = $event->tasks()->make([
            'title' => 'Prepare the venue',
            'due_at' => now()->addDay(),
            'priority' => 'medium',
            'status' => 'not_started',
        ]);
        $task->creator()->associate($leader);
        $task->assignee()->associate($leader);
        $task->save();

        $this->actingAs($admin)
            ->patch(route('admin.tasks.update', $task), [
                'title' => 'Updated venue plan',
                'description' => 'Revised by the platform team.',
                'assigned_to' => $assignee->getKey(),
                'due_at' => now()->subHour()->format('Y-m-d\TH:i'),
                'priority' => 'high',
                'status' => 'completed',
            ])
            ->assertSessionHasErrors('due_at');

        $this->assertSame('Prepare the venue', $task->fresh()->title);
        $this->assertSame($leader->getKey(), $task->fresh()->assigned_to);

        $this->patch(route('admin.tasks.update', $task), [
            'title' => 'Updated venue plan',
            'description' => 'Revised by the platform team.',
            'assigned_to' => $assignee->getKey(),
            'due_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'priority' => 'high',
            'status' => 'completed',
        ])
            ->assertRedirect(route('admin.tasks.edit', $task));

        $this->assertSame('Updated venue plan', $task->fresh()->title);
        $this->assertSame($assignee->getKey(), $task->fresh()->assigned_to);
        $this->assertSame(0, $assignee->notifications()->where('type', TaskUpdate::class)->count());

        $this->delete(route('admin.tasks.destroy', $task))->assertRedirect();
        $this->assertSoftDeleted($task);

        $this->post(route('admin.tasks.restore', $task))->assertRedirect();
        $this->assertNull($task->fresh()->deleted_at);
    }
}
