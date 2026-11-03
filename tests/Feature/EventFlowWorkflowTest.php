<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class EventFlowWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_sign_in_for_the_dashboard(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_visiting_the_root_is_redirected_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }

    public function test_user_can_sign_in_and_reaches_their_dashboard(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_guest_can_reset_password_from_the_email_link(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123')]);
        Notification::fake();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Forgot password?')
            ->assertSee(route('password.request'));

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Email me a reset link');

        $this->post(route('password.email'), ['email' => strtoupper($user->email)])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', 'If an account exists for this email, we will send a password reset link. If you requested one recently, wait a minute before trying again.');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->assertIsString($token);
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Choose a new');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your password has been updated. Sign in with your new password.');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123', $user->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Your password has been updated. Sign in with your new password.');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'NewPassword123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_password_reset_request_does_not_reveal_unknown_email_addresses(): void
    {
        Notification::fake();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'unknown@example.test'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', 'If an account exists for this email, we will send a password reset link. If you requested one recently, wait a minute before trying again.')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_password_reset_rejects_invalid_tokens_without_changing_the_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123')]);

        $this->from(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => 'invalid-token',
                'email' => $user->email,
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertRedirect(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
            ->assertSessionHasErrors([
                'email' => 'This password reset link is invalid or has expired.',
            ]);

        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));
        $this->assertGuest();
    }

    public function test_password_reset_rejects_weak_passwords(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123')]);
        $token = Password::broker()->createToken($user);

        $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'weak',
                'password_confirmation' => 'weak',
            ])
            ->assertRedirect(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertSessionHasErrors(['password']);

        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));
    }

    public function test_password_reset_rejects_unconfirmed_passwords(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123')]);
        $token = Password::broker()->createToken($user);

        $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'NewPassword123',
                'password_confirmation' => 'DifferentPassword123',
            ])
            ->assertRedirect(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertSessionHasErrors(['password']);

        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));
    }

    public function test_guest_can_register_and_is_signed_in_to_the_dashboard(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Create my account')
            ->assertSee('eventflow-mark.svg')
            ->assertDontSee('>ef</span>');

        $this->post(route('register.store'), [
            'name' => 'Jordan Rivera',
            'email' => 'JORDAN@EXAMPLE.TEST',
            'password' => 'first-password',
            'password_confirmation' => 'first-password',
        ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Jordan Rivera',
            'email' => 'jordan@example.test',
        ]);
        $this->assertNotSame('first-password', User::query()->where('email', 'jordan@example.test')->value('password'));
    }

    public function test_registration_rejects_duplicate_email_and_mismatched_passwords(): void
    {
        $existingUser = User::factory()->create(['email' => 'already@example.test']);

        $this->from(route('register'))
            ->post(route('register.store'), [
                'name' => 'New Member',
                'email' => strtoupper($existingUser->email),
                'password' => 'first-password',
                'password_confirmation' => 'different-password',
            ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_authenticated_user_cannot_open_registration_or_login_pages(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('register'))
            ->assertRedirect(route('dashboard'));

        $this->get(route('login'))
            ->assertRedirect(route('dashboard'));

        $this->get(route('password.request'))
            ->assertRedirect(route('dashboard'));

        $this->get(route('password.reset', [
            'token' => 'reset-token',
            'email' => 'member@example.test',
        ]))
            ->assertRedirect(route('dashboard'));
    }

    public function test_login_and_registration_pages_show_the_eventflow_brand_mark(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('eventflow-mark.svg')
            ->assertDontSee('>ef</span>');

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('eventflow-mark.svg')
            ->assertDontSee('>ef</span>');
    }

    public function test_user_can_sign_out_and_the_session_is_invalidated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_invalid_sign_in_uses_a_generic_error_message(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'unknown@example.test',
                'password' => 'incorrect',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'The email or password is incorrect.',
            ]);

        $this->assertGuest();
    }

    public function test_login_attempts_are_limited_after_five_failures(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('login'))->post(route('login.store'), [
                'email' => 'blocked@example.test',
                'password' => 'incorrect',
            ]);
        }

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'blocked@example.test',
                'password' => 'incorrect',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_user_can_create_an_event_and_becomes_its_leader(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('events.store'), $this->eventInput())
            ->assertRedirect();

        $event = Event::query()->where('title', 'Spring Arts Festival')->firstOrFail();
        $this->assertSame($user->id, $event->created_by);
        $this->assertDatabaseHas('event_members', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => 'leader',
        ]);
    }

    public function test_event_creation_rejects_an_end_time_before_the_start_time(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('events.create'))
            ->post(route('events.store'), $this->eventInput([
                'end_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
            ]))
            ->assertRedirect(route('events.create'))
            ->assertSessionHasErrors('end_at');

        $this->assertDatabaseMissing('events', ['title' => 'Spring Arts Festival']);
    }

    public function test_only_event_members_can_view_a_private_event(): void
    {
        $leader = User::factory()->create();
        $event = $this->createEventWithLeader($leader, ['title' => 'Spring Arts Festival']);

        $this->actingAs(User::factory()->create())
            ->get(route('events.show', $event))
            ->assertNotFound();
    }

    public function test_only_an_event_leader_can_edit_event_details(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();
        $event = $this->createEventWithLeader($leader, ['title' => 'Spring Arts Festival']);
        $event->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($member)
            ->put(route('events.update', $event), $this->eventInput(['title' => 'Unauthorized edit']))
            ->assertForbidden();

        $this->assertSame('Spring Arts Festival', $event->fresh()->title);

        $this->actingAs($leader)
            ->put(route('events.update', $event), $this->eventInput(['title' => 'Updated Arts Festival']))
            ->assertRedirect(route('events.show', $event));

        $this->assertSame('Updated Arts Festival', $event->fresh()->title);
    }

    public function test_event_leader_can_cancel_without_deleting_the_event(): void
    {
        $leader = User::factory()->create();
        $event = $this->createEventWithLeader($leader);

        $this->actingAs($leader)
            ->post(route('events.status', $event), ['status' => 'cancelled'])
            ->assertRedirect(route('events.show', $event));

        $this->assertSame('cancelled', $event->fresh()->status);
        $this->assertModelExists($event->fresh());
    }

    public function test_leader_can_create_assigned_and_unassigned_tasks(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();
        $event = $this->createEventWithLeader($leader);
        $event->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($leader)
            ->post(route('events.tasks.store', $event), $this->taskInput([
                'title' => 'Book the auditorium',
                'assigned_to' => $member->id,
            ]))
            ->assertRedirect(route('events.show', $event));

        $this->assertDatabaseHas('tasks', [
            'event_id' => $event->id,
            'created_by' => $leader->id,
            'assigned_to' => $member->id,
            'title' => 'Book the auditorium',
        ]);

        $this->post(route('events.tasks.store', $event), $this->taskInput([
            'title' => 'Prepare welcome signs',
            'assigned_to' => '',
        ]))
            ->assertRedirect(route('events.show', $event));

        $this->assertDatabaseHas('tasks', [
            'event_id' => $event->id,
            'title' => 'Prepare welcome signs',
            'assigned_to' => null,
        ]);
    }

    public function test_task_assignees_must_belong_to_the_event(): void
    {
        $leader = User::factory()->create();
        $outsider = User::factory()->create();
        $event = $this->createEventWithLeader($leader);

        $this->actingAs($leader)
            ->from(route('events.show', $event))
            ->post(route('events.tasks.store', $event), $this->taskInput([
                'assigned_to' => $outsider->id,
            ]))
            ->assertRedirect(route('events.show', $event))
            ->assertSessionHasErrors('assigned_to');

        $this->assertDatabaseMissing('tasks', ['event_id' => $event->id]);
    }

    public function test_assignee_can_update_only_their_task_status(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $event = $this->createEventWithLeader($leader);
        $event->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        $task = $this->createTask($event, $leader, $member, ['title' => 'Prepare the stage']);

        $this->actingAs($member)
            ->patch(route('tasks.update', $task), [
                'status' => 'in_progress',
                'title' => 'Changed by an assignee',
            ])
            ->assertRedirect(route('events.show', $event));

        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertSame('Prepare the stage', $task->fresh()->title);

        $this->actingAs($outsider)
            ->patch(route('tasks.update', $task), ['status' => 'completed'])
            ->assertNotFound();
    }

    public function test_assignee_cannot_submit_an_invalid_task_status(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();
        $event = $this->createEventWithLeader($leader);
        $event->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        $task = $this->createTask($event, $leader, $member);

        $this->actingAs($member)
            ->from(route('tasks.edit', $task))
            ->patch(route('tasks.update', $task), ['status' => 'done'])
            ->assertRedirect(route('tasks.edit', $task))
            ->assertSessionHasErrors('status');

        $this->assertSame('not_started', $task->fresh()->status);
    }

    public function test_leader_can_add_members_and_duplicate_membership_is_rejected(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();
        $event = $this->createEventWithLeader($leader);

        $this->actingAs($leader)
            ->post(route('events.members.store', $event), ['user_id' => $member->id, 'role' => 'leader'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('event_members', [
            'event_id' => $event->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $this->post(route('events.members.store', $event), ['user_id' => $member->id])
            ->assertSessionHasErrors('user_id');

        $anotherUser = User::factory()->create();

        $this->actingAs($member)
            ->post(route('events.members.store', $event), ['user_id' => $anotherUser->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('event_members', [
            'event_id' => $event->id,
            'user_id' => $anotherUser->id,
        ]);
    }

    public function test_member_removal_unassigns_tasks_without_deleting_them(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();
        $event = $this->createEventWithLeader($leader);
        $event->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        $task = $this->createTask($event, $leader, $member);
        $archivedTask = $this->createTask($event, $leader, $member, ['title' => 'Archived assigned work']);
        $archivedTask->delete();

        $this->actingAs($leader)
            ->delete(route('events.members.destroy', [$event, $member]))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'assigned_to' => null,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $archivedTask->id,
            'assigned_to' => null,
        ]);
        $this->assertDatabaseMissing('event_members', [
            'event_id' => $event->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_calendar_renders_member_events_and_assigned_deadlines(): void
    {
        $leader = User::factory()->create();
        $event = $this->createEventWithLeader($leader, [
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(2)->addHours(2),
        ]);
        $task = $this->createTask($event, $leader, $leader, [
            'title' => 'Prepare the welcome table',
            'due_at' => now()->addDay(),
        ]);

        $this->actingAs($leader)
            ->get(route('calendar', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertSee($event->title)
            ->assertSee($task->title);
    }

    public function test_authenticated_leader_can_open_each_workspace_page(): void
    {
        $leader = User::factory()->create();
        $event = $this->createEventWithLeader($leader);
        $task = $this->createTask($event, $leader, $leader);
        $this->actingAs($leader);

        foreach ([
            route('dashboard'),
            route('events.index'),
            route('events.create'),
            route('events.show', $event),
            route('events.edit', $event),
            route('tasks.index'),
            route('tasks.edit', $task),
            route('calendar'),
            route('profile.edit'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_event_workspace_escapes_user_supplied_html(): void
    {
        $leader = User::factory()->create();
        $event = $this->createEventWithLeader($leader, [
            'title' => '<script>alert(1)</script>',
            'description' => '<script>alert(2)</script>',
        ]);

        $this->actingAs($leader)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert', false);
    }

    public function test_dashboard_statistics_only_include_the_authenticated_users_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $event = $this->createEventWithLeader($user, ['status' => 'published']);
        $event->members()->attach($otherUser->id, ['role' => 'member', 'joined_at' => now()]);
        $this->createTask($event, $user, $user, ['status' => 'completed']);
        $this->createTask($event, $user, $user, ['due_at' => now()->subDay()]);
        $otherEvent = $this->createEventWithLeader($otherUser);
        $this->createTask($otherEvent, $otherUser, $otherUser, ['due_at' => now()->subDay()]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Events you\'re in', false)
            ->assertSee('<strong>1</strong><small>Across your school community</small>', false)
            ->assertSee('<strong>2</strong><small>1 completed so far</small>', false)
            ->assertSee('<strong>1</strong><small>Task needs your attention</small>', false)
            ->assertSee($event->title)
            ->assertDontSee($otherEvent->title);
    }

    public function test_user_can_update_their_profile_and_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Jordan Rivera',
                'email' => 'jordan@example.test',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Jordan Rivera', $user->fresh()->name);
        $this->assertNull($user->fresh()->email_verified_at);

        $this->put(route('profile.password'), [
            'current_password' => 'password',
            'password' => 'StrongPass9!',
            'password_confirmation' => 'StrongPass9!',
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('StrongPass9!', $user->fresh()->password));
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
            'category' => 'Arts',
            'location' => 'Main Hall',
            'start_at' => now()->addDays(4)->startOfHour()->format('Y-m-d\TH:i'),
            'end_at' => now()->addDays(4)->startOfHour()->addHours(2)->format('Y-m-d\TH:i'),
            'status' => 'draft',
        ], $overrides);
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
    private function createEventWithLeader(User $leader, array $attributes = []): Event
    {
        $event = Event::factory()->for($leader, 'creator')->create($attributes);
        $event->members()->attach($leader->id, ['role' => 'leader', 'joined_at' => now()]);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTask(Event $event, User $creator, ?User $assignee, array $attributes = []): Task
    {
        $task = $event->tasks()->make(array_replace([
            'title' => 'Plan event logistics',
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
