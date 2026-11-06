<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Notifications\EventScheduleReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventScheduleReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sends_all_event_schedule_stages_once_to_members(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
        $member = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->createPublishedEvent($member, [
            'title' => 'Tomorrow event',
            'start_at' => now()->addHours(24),
            'end_at' => now()->addHours(26),
        ]);
        $this->createPublishedEvent($member, [
            'title' => 'One hour event',
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(2),
        ]);
        $this->createPublishedEvent($member, [
            'title' => 'Starting event',
            'start_at' => now(),
            'end_at' => now()->addHours(2),
        ]);
        $this->createPublishedEvent($member, [
            'title' => 'Passed event',
            'start_at' => now()->subHours(2),
            'end_at' => now(),
        ]);

        $this->artisan('events:send-schedule-notifications')->assertSuccessful();
        $this->artisan('events:send-schedule-notifications')->assertSuccessful();

        $notifications = $member->notifications()->where('type', EventScheduleReminder::class)->get();
        $stages = $notifications->pluck('data.stage')->sort()->values()->all();

        $this->assertSame(['1_hour', '24_hours', 'passed', 'started'], $stages);
        $this->assertCount(4, $notifications);
        $this->assertSame(0, $otherUser->notifications()->count());
    }

    public function test_draft_cancelled_and_deleted_events_do_not_send_schedule_reminders(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
        $member = User::factory()->create();
        $draft = $this->createEvent($member, ['status' => 'draft', 'start_at' => now(), 'end_at' => now()->addHour()]);
        $cancelled = $this->createEvent($member, ['status' => 'cancelled', 'start_at' => now(), 'end_at' => now()->addHour()]);
        $deleted = $this->createPublishedEvent($member, ['title' => 'Deleted event', 'start_at' => now(), 'end_at' => now()->addHour()]);
        $deleted->delete();

        $this->artisan('events:send-schedule-notifications')->assertSuccessful();

        $this->assertCount(0, $member->notifications()->where('type', EventScheduleReminder::class)->get());
        $this->assertNull($draft->deleted_at);
        $this->assertNull($cancelled->deleted_at);
    }

    public function test_member_can_disable_event_schedule_reminders(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(12, 0));
        $member = User::factory()->create([
            'notification_preferences' => [
                'task_assignments' => true,
                'task_status_updates' => true,
                'deadline_reminders' => true,
                'event_updates' => false,
            ],
        ]);
        $this->createPublishedEvent($member, [
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(2),
        ]);

        $this->artisan('events:send-schedule-notifications')->assertSuccessful();

        $this->assertCount(0, $member->notifications()->where('type', EventScheduleReminder::class)->get());
    }

    private function createPublishedEvent(User $member, array $attributes = []): Event
    {
        return $this->createEvent($member, array_replace(['status' => 'published'], $attributes));
    }

    private function createEvent(User $member, array $attributes = []): Event
    {
        $event = Event::factory()->for($member, 'creator')->create(array_replace([
            'status' => 'draft',
            'start_at' => now()->addHours(24),
            'end_at' => now()->addHours(26),
        ], $attributes));
        $event->members()->attach($member->getKey(), ['role' => 'member', 'joined_at' => now()]);

        return $event;
    }
}
