<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_belongs_to_its_creator(): void
    {
        $creator = User::factory()->create();
        $event = Event::factory()->for($creator, 'creator')->create();

        $this->assertTrue($event->creator->is($creator));
        $this->assertModelExists($event);
    }
}
