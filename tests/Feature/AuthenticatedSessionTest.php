<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_private_pages(): void
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
        $task->assignee()->associate($leader);
        $task->save();

        $this->get('/')
            ->assertOk()
            ->assertViewIs('auth.login');

        foreach ([
            route('dashboard'),
            route('events.show', $event),
            route('tasks.edit', $task),
            route('calendar'),
            route('profile.edit'),
        ] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_authenticated_pages_and_logout_response_are_not_cacheable(): void
    {
        $user = User::factory()->create();

        $dashboard = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertStringContainsString('no-store', $dashboard->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $dashboard->headers->get('Pragma'));

        $logout = $this->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertStringContainsString('no-store', $logout->headers->get('Cache-Control'));
        $this->assertGuest();
    }
}
