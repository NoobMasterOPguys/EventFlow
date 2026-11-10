<?php

namespace App\Notifications;

use App\Channels\BrevoTransactionalMailChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BrevoPasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $token
    ) {}

    public function via(object $notifiable): array
    {
        return [
            BrevoTransactionalMailChannel::class,
        ];
    }
}