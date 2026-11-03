<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        return $user->events()->whereKey($task->event_id)->exists();
    }

    public function update(User $user, Task $task): bool
    {
        return $user->eventMemberships()
            ->where('event_id', $task->event_id)
            ->where(function (Builder $query) use ($user, $task): void {
                $query->where('role', 'leader')
                    ->orWhere(function (Builder $query) use ($user, $task): void {
                        $query->where('role', 'member')
                            ->where('user_id', $user->getKey())
                            ->where('user_id', $task->assigned_to);
                    });
            })
            ->exists();
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->eventMemberships()
            ->where('event_id', $task->event_id)
            ->where('role', 'leader')
            ->exists();
    }
}
