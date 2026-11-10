<?php

namespace App\Channels;

use App\Models\User;
use App\Notifications\BrevoPasswordResetNotification;
use App\Services\BrevoTransactionalMailer;

class BrevoTransactionalMailChannel
{
    public function __construct(
        private readonly BrevoTransactionalMailer $mailer
    ) {}

    public function send(
        User $notifiable,
        BrevoPasswordResetNotification $notification
    ): void {
        $this->mailer->sendPasswordReset(
            $notifiable,
            $notification->token
        );
    }
}