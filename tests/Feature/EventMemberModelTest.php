<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventMember;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventMemberModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_become_an_event_member(): void
    {
        $event = Event::factory()->create();
        $user = User::factory()->create();
        $membership = EventMember::factory()->for($event)->for($user)->create();

        $this->assertTrue($membership->event->is($event));
        $this->assertTrue($membership->user->is($user));
        $this->assertDatabaseHas('event_members', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => 'member',
        ]);
    }

    public function test_event_can_have_multiple_leaders(): void
    {
        $event = Event::factory()->create();
        $firstLeader = User::factory()->create();
        $secondLeader = User::factory()->create();

        EventMember::factory()->for($event)->for($firstLeader)->leader()->create();
        EventMember::factory()->for($event)->for($secondLeader)->leader()->create();

        $this->assertCount(2, $event->leaders);
        $this->assertCount(1, $firstLeader->leaderMemberships);
        $this->assertCount(1, $secondLeader->leaderMemberships);
    }

    public function test_duplicate_event_membership_is_rejected(): void
    {
        $membership = EventMember::factory()->create();

        $this->expectException(QueryException::class);

        EventMember::factory()->create([
            'event_id' => $membership->event_id,
            'user_id' => $membership->user_id,
        ]);
    }
}
