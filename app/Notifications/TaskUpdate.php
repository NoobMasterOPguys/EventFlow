<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Notifications\Notification;

class TaskUpdate extends Notification
{
    public function __construct(public Task $task, public string $kind) {}

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
            'notification_kind' => $this->kind,
            'task_id' => $this->task->getKey(),
            'task_title' => $this->task->title,
            'event_title' => $this->task->event->title,
            'due_at' => $this->task->due_at?->toIso8601String(),
            'deadline_label' => match ($this->kind) {
                'assigned' => 'A task was assigned to you',
                'reassigned' => 'A task was reassigned to you',
                default => 'Task status changed to '.str($this->task->status)->replace('_', ' ')->title(),
            },
        ];
    }
}
