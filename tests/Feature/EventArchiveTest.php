<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_leader_can_archive_an_event_and_preserve_its_tasks(): void
    {
        $leader = User::factory()->create();
        $event = Event::factory()->for($leader, 'creator')->create();
        $event->members()->attach($leader->getKey(), ['role' => 'leader', 'joined_at' => now()]);
        $task = $event->tasks()->make([
            'title' => 'Prepare the room',
            'priority' => 'medium',
            'status' => 'not_started',
        ]);
        $task->creator()->associate($leader);
        $task->save();

        $this->actingAs($leader)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee(route('events.destroy', $event), false);

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('Archive event');

        $this->delete(route('events.destroy', $event))
            ->assertRedirect(route('events.index'))
            ->assertSessionHas('success', 'Event archived. Its history is preserved and an administrator can restore it.');

        $this->assertSoftDeleted($event);
        $this->assertModelExists($task);
    }

    public function test_event_member_cannot_archive_the_event(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();
        $event = Event::factory()->for($leader, 'creator')->create();
        $event->members()->attach($leader->getKey(), ['role' => 'leader', 'joined_at' => now()]);
        $event->members()->attach($member->getKey(), ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($member)
            ->delete(route('events.destroy', $event))
            ->assertForbidden();

        $this->assertModelExists($event);
    }
}
