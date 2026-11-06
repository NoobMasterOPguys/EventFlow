<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Notifications\Notification;

class EventUpdate extends Notification
{
    public function __construct(public Event $event, public string $kind) {}

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
            'notification_kind' => 'event.'.$this->kind,
            'event_id' => $this->event->getKey(),
            'event_title' => $this->event->title,
            'start_at' => $this->event->start_at?->toIso8601String(),
            'event_status' => $this->event->status,
            'deadline_label' => match ($this->kind) {
                'member_added' => 'You were added to an event',
                'status_changed' => 'Event status changed',
                default => 'Event details were updated',
            },
        ];
    }
}
