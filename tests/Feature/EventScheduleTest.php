<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_event_schedule_must_be_in_the_future(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
        $leader = User::factory()->create();

        $this->actingAs($leader)
            ->get(route('events.create'))
            ->assertOk()
            ->assertSee('min="2026-11-04T12:01"', false);

        $this->from(route('events.create'))
            ->post(route('events.store'), $this->eventInput([
                'start_at' => now()->subHours(2)->format('Y-m-d\TH:i'),
                'end_at' => now()->subHour()->format('Y-m-d\TH:i'),
            ]))
            ->assertRedirect(route('events.create'))
            ->assertSessionHasErrors([
                'start_at' => 'Your event schedule must be later than the current time. Please select a future date and time.',
                'end_at' => 'Your event schedule must be later than the current time. Please select a future date and time.',
            ]);

        $this->assertDatabaseMissing('events', ['title' => 'Spring Arts Festival']);
    }

    public function test_historical_event_details_can_be_edited_without_changing_its_schedule(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
        $leader = User::factory()->create();
        $startAt = now()->subDays(2)->setSecond(15);
        $endAt = now()->subDays(2)->addHours(2)->setSecond(30);
        $event = Event::factory()->for($leader, 'creator')->create([
            'start_at' => $startAt,
            'end_at' => $endAt,
        ]);
        $event->members()->attach($leader->getKey(), ['role' => 'leader', 'joined_at' => now()]);

        $this->actingAs($leader)
            ->get(route('events.edit', $event))
            ->assertOk()
            ->assertDontSee('min="', false);

        $this->put(route('events.update', $event), $this->eventInput([
            'title' => 'Updated historical event',
            'description' => 'Historical notes have been updated.',
            'start_at' => $startAt->format('Y-m-d\TH:i'),
            'end_at' => $endAt->format('Y-m-d\TH:i'),
        ]))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasNoErrors();

        $this->assertSame('Updated historical event', $event->fresh()->title);
        $this->assertSame($startAt->format('Y-m-d H:i:s'), $event->fresh()->start_at->format('Y-m-d H:i:s'));
        $this->assertSame($endAt->format('Y-m-d H:i:s'), $event->fresh()->end_at->format('Y-m-d H:i:s'));
    }

    public function test_historical_event_schedule_cannot_be_changed_to_another_past_time(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
        $leader = User::factory()->create();
        $event = Event::factory()->for($leader, 'creator')->create([
            'start_at' => now()->subDays(2),
            'end_at' => now()->subDays(2)->addHours(2),
        ]);
        $event->members()->attach($leader->getKey(), ['role' => 'leader', 'joined_at' => now()]);

        $this->actingAs($leader)
            ->put(route('events.update', $event), $this->eventInput([
                'title' => 'Changed event time',
                'start_at' => now()->subHours(3)->format('Y-m-d\TH:i'),
                'end_at' => now()->subHours(2)->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHasErrors(['start_at', 'end_at']);

        $this->assertSame($event->title, $event->fresh()->title);
    }

    public function test_unchanged_historical_start_can_keep_its_time_when_the_end_is_rescheduled(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
        $leader = User::factory()->create();
        $startAt = now()->subDays(2);
        $event = Event::factory()->for($leader, 'creator')->create([
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addHours(2),
        ]);
        $event->members()->attach($leader->getKey(), ['role' => 'leader', 'joined_at' => now()]);

        $this->actingAs($leader)
            ->put(route('events.update', $event), $this->eventInput([
                'start_at' => $startAt->format('Y-m-d\TH:i'),
                'end_at' => now()->addDay()->format('Y-m-d\TH:i'),
            ]))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasNoErrors();

        $this->assertSame($startAt->format('Y-m-d H:i:s'), $event->fresh()->start_at->format('Y-m-d H:i:s'));
        $this->assertSame(now()->addDay()->format('Y-m-d H:i'), $event->fresh()->end_at->format('Y-m-d H:i'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function eventInput(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Spring Arts Festival',
            'description' => 'A day for student creativity.',
            'category' => 'Academic',
            'location' => 'Main Hall',
            'start_at' => now()->addDays(4)->startOfHour()->format('Y-m-d\TH:i'),
            'end_at' => now()->addDays(4)->startOfHour()->addHours(2)->format('Y-m-d\TH:i'),
            'status' => 'draft',
        ], $overrides);
    }
}
