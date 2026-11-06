<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\User;
use App\Notifications\EventScheduleReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Signature('events:send-schedule-notifications')]
#[Description('Send in-app reminders for event schedules')]
class SendEventScheduleNotifications extends Command
{
    public function handle(): int
    {
        $now = Carbon::now(config('app.timezone'));
        $sent = 0;

        $sent += $this->sendForWindow('24_hours', $now->copy()->addHours(24)->subMinute(), $now->copy()->addHours(24), 'start_at');
        $sent += $this->sendForWindow('1_hour', $now->copy()->addHour()->subMinute(), $now->copy()->addHour(), 'start_at');
        $sent += $this->sendForWindow('started', $now->copy()->subMinute(), $now, 'start_at');
        $sent += $this->sendForWindow('passed', $now->copy()->subMinute(), $now, 'end_at');

        $this->info("Sent {$sent} event schedule notification(s).");

        return self::SUCCESS;
    }

    private function sendForWindow(string $stage, Carbon $start, Carbon $end, string $column): int
    {
        $sent = 0;

        Event::query()
            ->where('status', 'published')
            ->whereBetween($column, [$start, $end])
            ->with('members')
            ->orderBy('id')
            ->chunkById(100, function ($events) use ($stage, &$sent): void {
                foreach ($events as $event) {
                    foreach ($event->members as $member) {
                        if ($this->sendReminder($member, $event, $stage)) {
                            $sent++;
                        }
                    }
                }
            });

        return $sent;
    }

    private function sendReminder(User $member, Event $event, string $stage): bool
    {
        if (! $member->isActive() || ! $member->receivesNotificationFor('event_updates')) {
            return false;
        }

        $notification = new EventScheduleReminder($event, $stage);
        $notification->via($member);

        return DB::transaction(function () use ($member, $notification): bool {
            User::query()->whereKey($member->getKey())->lockForUpdate()->firstOrFail();

            if (DB::table('notifications')->where('id', $notification->id)->exists()) {
                return false;
            }

            $member->notify($notification);

            return true;
        });
    }
}
