<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventMember;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_belongs_to_its_event(): void
    {
        $event = Event::factory()->create();
        $task = Task::factory()->for($event)->create();

        $this->assertTrue($task->event->is($event));
    }

    public function test_task_can_be_assigned_to_a_member_of_its_event(): void
    {
        $event = Event::factory()->create();
        $member = User::factory()->create();
        $task = Task::factory()->for($event)->assignedToMember($member)->create();

        $this->assertTrue($task->assignee->is($member));
        $this->assertTrue($member->assignedTasks->contains($task));
        $this->assertDatabaseHas('event_members', [
            'event_id' => $event->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_task_may_remain_unassigned(): void
    {
        $task = Task::factory()->create();

        $this->assertNull($task->assigned_to);
        $this->assertNull($task->assignee);
    }

    public function test_database_rejects_assigning_a_non_member_to_an_event_task(): void
    {
        $event = Event::factory()->create();
        $member = User::factory()->create();
        $nonMember = User::factory()->create();
        EventMember::factory()->for($event)->for($member)->create();
        $task = Task::factory()->for($event)->create();

        $this->assertDatabaseMissing('event_members', [
            'event_id' => $event->id,
            'user_id' => $nonMember->id,
        ]);

        $this->expectException(QueryException::class);

        DB::table('tasks')->where('id', $task->id)->update([
            'assigned_to' => $nonMember->id,
        ]);
    }

    public function test_soft_deleted_event_is_excluded_from_normal_queries(): void
    {
        $event = Event::factory()->create();

        $event->delete();

        $this->assertSoftDeleted($event);
        $this->assertFalse(Event::query()->whereKey($event->id)->exists());
    }

    public function test_soft_deleted_task_is_excluded_from_normal_queries(): void
    {
        $task = Task::factory()->create();

        $task->delete();

        $this->assertSoftDeleted($task);
        $this->assertFalse(Task::query()->whereKey($task->id)->exists());
    }
}
