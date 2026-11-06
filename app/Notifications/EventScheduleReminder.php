<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\User;
use Illuminate\Notifications\Notification;

class EventScheduleReminder extends Notification
{
    public function __construct(public Event $event, public string $stage)
    {
        $this->setId();
    }

    public function via(object $notifiable): array
    {
        $this->setId($notifiable instanceof User ? $notifiable->getKey() : null);

        return ['database'];
    }

    private function setId(?int $userId = null): void
    {
        $schedule = $this->stage === 'passed' ? $this->event->end_at : $this->event->start_at;
        $timestamp = $schedule?->utc()->format('Y-m-d\TH:i:s.u\Z') ?? '';
        $hash = md5(implode('|', [
            $this->event->getKey(),
            $this->stage,
            $timestamp,
            $userId,
        ]));
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

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_kind' => 'event.schedule_reminder',
            'event_id' => $this->event->getKey(),
            'event_title' => $this->event->title,
            'start_at' => $this->event->start_at?->toIso8601String(),
            'end_at' => $this->event->end_at?->toIso8601String(),
            'stage' => $this->stage,
            'deadline_label' => match ($this->stage) {
                '24_hours' => 'Event starts in 24 hours',
                '1_hour' => 'Event starts in 1 hour',
                'started' => 'Event has started',
                'passed' => 'Event has passed',
                default => 'Event reminder',
            },
        ];
    }
}
