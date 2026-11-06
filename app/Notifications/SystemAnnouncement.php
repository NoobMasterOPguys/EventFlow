<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Notifications\Notification;

class SystemAnnouncement extends Notification
{
    public function __construct(public Announcement $announcement) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, int|string>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_kind' => 'system.announcement',
            'announcement_id' => $this->announcement->getKey(),
            'title' => $this->announcement->title,
            'body' => $this->announcement->body,
        ];
    }
}
