<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_delete_an_account(): void
    {
        $this->delete(route('profile.destroy'), ['account_password' => 'password'])
            ->assertRedirect(route('login'));
    }

    public function test_profile_security_page_shows_the_account_deletion_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Delete account')
            ->assertSee('action="'.route('profile.destroy').'"', false);
    }

    public function test_profile_security_page_requests_successors_for_work_that_will_be_transferred(): void
    {
        $user = User::factory()->create();
        $member = User::factory()->create(['name' => 'Morgan Teammate']);
        $event = Event::factory()->for($user, 'creator')->create(['title' => 'Community day']);
        $event->members()->attach($user->getKey(), ['role' => 'leader', 'joined_at' => now()]);
        $event->members()->attach($member->getKey(), ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Transfer “Community day” and your task history to')
            ->assertSee('Morgan Teammate')
            ->assertSee('name="event_successors['.$event->getKey().']"', false);
    }

    public function test_user_can_update_in_app_notification_preferences(): void
    {
        $user = User::factory()->create();
        $preferences = [
            'task_assignments' => '0',
            'task_status_updates' => '1',
            'deadline_reminders' => '0',
            'event_updates' => '1',
        ];

        $this->actingAs($user)
            ->patch(route('profile.notifications'), ['notification_preferences' => $preferences])
            ->assertRedirect()
            ->assertSessionHas('success', 'Notification preferences updated.');

        $this->assertSame([
            'task_assignments' => false,
            'task_status_updates' => true,
            'deadline_reminders' => false,
            'event_updates' => true,
        ], $user->fresh()->notification_preferences);
    }

    public function test_notification_preferences_must_include_all_supported_choices(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.notifications'), [
                'notification_preferences' => ['task_assignments' => false],
            ])
            ->assertSessionHasErrors([
                'notification_preferences.task_status_updates',
                'notification_preferences.deadline_reminders',
                'notification_preferences.event_updates',
            ]);

        $this->assertTrue($user->fresh()->notification_preferences['task_assignments']);
    }

    public function test_notification_preferences_reject_unrecognized_keys(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.notifications'), [
                'notification_preferences' => [
                    'task_assignments' => true,
                    'task_status_updates' => true,
                    'deadline_reminders' => true,
                    'event_updates' => true,
                    'system_role' => 'admin',
                ],
            ])
            ->assertSessionHasErrors('notification_preferences');

        $this->assertSame('user', $user->fresh()->system_role);
    }

    public function test_account_deletion_requires_the_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['account_password' => 'incorrect-password'])
            ->assertSessionHasErrors('account_password');

        $this->assertModelExists($user);
        $this->assertAuthenticatedAs($user);
    }

    public function test_account_deletion_archives_an_owned_event_without_an_active_successor(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user, 'creator')->create();
        $event->members()->attach($user->getKey(), ['role' => 'leader', 'joined_at' => now()]);

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['account_password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your account has been deleted. Shared events and task history were preserved.');

        $this->assertSoftDeleted($event);
        $this->assertSoftDeleted($user);
        $this->assertDatabaseMissing('event_members', ['user_id' => $user->getKey()]);
        $this->assertGuest();
    }

    public function test_account_deletion_transfers_authored_tasks_to_a_selected_active_member(): void
    {
        $user = User::factory()->create();
        $successor = User::factory()->create();
        $event = Event::factory()->for($successor, 'creator')->create();
        $event->members()->attach($successor->getKey(), ['role' => 'leader', 'joined_at' => now()]);
        $task = $event->tasks()->make([
            'title' => 'Prepare the room',
            'priority' => 'medium',
            'status' => 'not_started',
        ]);
        $task->creator()->associate($user);
        $task->save();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), [
                'account_password' => 'password',
                'event_successors' => [$event->getKey() => $successor->getKey()],
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your account has been deleted. Shared events and task history were preserved.');

        $this->assertSoftDeleted($user);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->getKey(),
            'created_by' => $successor->getKey(),
        ]);
    }

    public function test_account_deletion_removes_membership_and_unassigns_tasks_without_affecting_the_event(): void
    {
        $user = User::factory()->create();
        $eventOwner = User::factory()->create();
        $event = Event::factory()->for($eventOwner, 'creator')->create();
        $event->members()->attach($eventOwner->getKey(), ['role' => 'leader', 'joined_at' => now()]);
        $event->members()->attach($user->getKey(), [
            'role' => 'member',
            'joined_at' => now(),
        ]);
        $task = $event->tasks()->make([
            'title' => 'Bring supplies',
            'priority' => 'medium',
            'status' => 'not_started',
        ]);
        $task->creator()->associate($eventOwner);
        $task->assignee()->associate($user);
        $task->save();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['account_password' => 'password'])
            ->assertRedirect(route('login'));

        $this->assertSoftDeleted($user);
        $this->assertModelExists($event);
        $this->assertDatabaseMissing('event_members', [
            'event_id' => $event->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->getKey(),
            'assigned_to' => null,
        ]);
    }

    public function test_account_deletion_archives_another_users_event_when_the_departing_user_is_its_only_member(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $event = Event::factory()->for($owner, 'creator')->create();
        $event->members()->attach($user->getKey(), ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['account_password' => 'password'])
            ->assertRedirect(route('login'));

        $this->assertSoftDeleted($user);
        $this->assertSoftDeleted($event);
        $this->assertDatabaseMissing('event_members', [
            'event_id' => $event->getKey(),
            'user_id' => $user->getKey(),
        ]);
    }

    public function test_account_deletion_transfers_owned_event_and_task_history_to_the_selected_teammate(): void
    {
        $user = User::factory()->create();
        $successor = User::factory()->create();
        $event = Event::factory()->for($user, 'creator')->create();
        $event->members()->attach($user->getKey(), ['role' => 'leader', 'joined_at' => now()]);
        $event->members()->attach($successor->getKey(), ['role' => 'member', 'joined_at' => now()]);
        $task = $event->tasks()->make([
            'title' => 'Set up the room',
            'priority' => 'high',
            'status' => 'not_started',
        ]);
        $task->creator()->associate($user);
        $task->assignee()->associate($user);
        $task->save();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), [
                'account_password' => 'password',
                'event_successors' => [$event->getKey() => $successor->getKey()],
            ])
            ->assertRedirect(route('login'));

        $this->assertSoftDeleted($user);
        $this->assertDatabaseHas('events', [
            'id' => $event->getKey(),
            'created_by' => $successor->getKey(),
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('event_members', [
            'event_id' => $event->getKey(),
            'user_id' => $successor->getKey(),
            'role' => 'leader',
        ]);
        $this->assertDatabaseMissing('event_members', [
            'event_id' => $event->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->getKey(),
            'created_by' => $successor->getKey(),
            'assigned_to' => null,
        ]);
    }

    public function test_account_deletion_rejects_a_successor_who_is_not_an_active_event_member(): void
    {
        $user = User::factory()->create();
        $member = User::factory()->create();
        $otherUser = User::factory()->create();
        $event = Event::factory()->for($user, 'creator')->create();
        $event->members()->attach($user->getKey(), ['role' => 'leader', 'joined_at' => now()]);
        $event->members()->attach($member->getKey(), ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'account_password' => 'password',
                'event_successors' => [$event->getKey() => $otherUser->getKey()],
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('event_successors.'.$event->getKey());

        $this->assertModelExists($user);
        $this->assertDatabaseHas('events', [
            'id' => $event->getKey(),
            'created_by' => $user->getKey(),
            'deleted_at' => null,
        ]);
    }

    public function test_user_can_delete_account_when_no_records_are_linked(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['account_password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your account has been deleted. Shared events and task history were preserved.');

        $this->assertSoftDeleted($user);
        $this->assertGuest();
    }

    public function test_user_can_register_again_with_the_same_email_after_account_deletion(): void
    {
        $email = 'returning-user@example.com';
        $user = User::factory()->create(['email' => $email]);
        Password::sendResetLink(['email' => $email]);

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['account_password' => 'password'])
            ->assertRedirect(route('login'));

        $deletedUser = User::withTrashed()->findOrFail($user->getKey());
        $this->assertSoftDeleted($deletedUser);
        $this->assertSame('Deleted user', $deletedUser->name);
        $this->assertNotSame($email, $deletedUser->email);
        $this->assertStringEndsWith('@deleted.invalid', $deletedUser->email);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $email]);

        $this->post(route('login.store'), [
            'email' => $email,
            'password' => 'password',
        ])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('register.store'), [
            'name' => 'Returning User',
            'email' => $email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your account is ready. Sign in with your new credentials to continue.');

        $this->assertGuest();
        $newUser = User::query()->where('email', $email)->firstOrFail();
        $this->assertNotSame($user->getKey(), $newUser->getKey());
        $this->assertSame('Returning User', $newUser->name);
        $this->assertTrue(Hash::check('NewPassword123', $newUser->password));

        $this->post(route('login.store'), [
            'email' => $email,
            'password' => 'NewPassword123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($newUser);
    }
}
