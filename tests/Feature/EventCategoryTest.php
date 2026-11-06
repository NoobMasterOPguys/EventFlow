<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_category_is_a_controlled_dropdown_and_unknown_values_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('events.create'))
            ->assertOk()
            ->assertSee('<select id="category" name="category">', false)
            ->assertSee('Select a category')
            ->assertSee('Student Organization');

        $this->from(route('events.create'))
            ->post(route('events.store'), $this->eventInput(['category' => 'Unlisted category']))
            ->assertRedirect(route('events.create'))
            ->assertSessionHasErrors('category')
            ->assertSessionHas('_old_input.category', 'Unlisted category');

        $this->assertDatabaseMissing('events', ['title' => 'Spring Arts Festival']);
    }

    public function test_existing_unknown_category_is_preserved_when_an_event_is_edited(): void
    {
        $leader = User::factory()->create();
        $event = Event::factory()->for($leader, 'creator')->create([
            'category' => 'Arts & Culture',
        ]);
        $event->members()->attach($leader->getKey(), ['role' => 'leader', 'joined_at' => now()]);

        $this->actingAs($leader)
            ->get(route('events.edit', $event))
            ->assertOk()
            ->assertSee('Arts & Culture');

        $this->put(route('events.update', $event), $this->eventInput([
            'title' => 'Updated event title',
            'category' => 'Arts & Culture',
        ]))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasNoErrors();

        $this->assertSame('Arts & Culture', $event->fresh()->category);
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
