<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Notifications\Notification;

class TaskDeadlineReminder extends Notification
{
    public function __construct(public Task $task, public string $stage)
    {
        $dueAt = $task->due_at?->utc()->format('Y-m-d\TH:i:s.u\Z') ?? '';
        $hash = md5(implode('|', [$task->getKey(), $task->assigned_to, $stage, $dueAt]));
        $hash[12] = '5';
        $hash[16] = 'a';
        $this->id = implode('-', [
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        ]);
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'task_id' => $this->task->getKey(),
            'task_title' => $this->task->title,
            'event_title' => $this->task->event->title,
            'due_at' => $this->task->due_at?->toIso8601String(),
            'stage' => $this->stage,
            'deadline_label' => match (true) {
                $this->stage === '1_hour' => 'Due within 1 hour',
                str_ends_with($this->stage, '_hours') => 'Due within '.str_replace('_hours', '', $this->stage).' hours',
                default => 'Deadline reached',
            },
        ];
    }
}
