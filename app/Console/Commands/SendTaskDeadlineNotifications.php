<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskDeadlineReminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

#[Signature('tasks:send-deadline-notifications')]
#[Description('Send in-app reminders for upcoming task deadlines')]
class SendTaskDeadlineNotifications extends Command
{
    public function handle(): int
    {
        SystemSetting::apply();
        $now = CarbonImmutable::now(config('app.timezone'));
        $sent = 0;
        $reminderHours = (int) SystemSetting::get('deadline_reminder_hours', 24);
        $sent += $this->sendForWindow(
            $reminderHours === 1 ? '1_hour' : $reminderHours.'_hours',
            $now->addHours($reminderHours)->subMinute(),
            $now->addHours($reminderHours),
        );
        if ($reminderHours !== 1) {
            $sent += $this->sendForWindow('1_hour', $now->addHour()->subMinute(), $now->addHour());
        }
        $sent += $this->sendForWindow('deadline', $now->subMinute(), $now);

        $this->info("Sent {$sent} task deadline notification(s).");

        return self::SUCCESS;
    }

    private function sendForWindow(string $stage, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $sent = 0;

        Task::query()
            ->whereNotNull('assigned_to')
            ->where('status', '!=', 'completed')
            ->whereBetween('due_at', [$start, $end])
            ->whereHas('event.members', fn (Builder $query): Builder => $query->whereColumn('users.id', 'tasks.assigned_to'))
            ->with(['assignee', 'event'])
            ->orderBy('id')
            ->chunkById(100, function ($tasks) use ($stage, &$sent): void {
                foreach ($tasks as $task) {
                    if ($this->sendReminder($task, $stage)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    private function sendReminder(Task $task, string $stage): bool
    {
        $assignee = $task->assignee;

        if (
            $assignee === null
            || $task->due_at === null
            || ! $assignee->receivesNotificationFor('deadline_reminders')
        ) {
            return false;
        }

        $notification = new TaskDeadlineReminder($task, $stage);

        return DB::transaction(function () use ($assignee, $notification): bool {
            User::query()->whereKey($assignee->getKey())->lockForUpdate()->firstOrFail();

            if (DB::table('notifications')->where('id', $notification->id)->exists()) {
                return false;
            }

            $assignee->notify($notification);

            return true;
        });
    }
}
