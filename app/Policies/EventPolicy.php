<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Event $event): bool
    {
        return $user->events()->whereKey($event->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Event $event): bool
    {
        return $user->eventMemberships()
            ->where('event_id', $event->getKey())
            ->where('role', 'leader')
            ->exists();
    }
}
