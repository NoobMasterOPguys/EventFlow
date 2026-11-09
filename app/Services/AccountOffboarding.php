<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountOffboarding
{
    /**
     * @return Collection<int, Event>
     */
    public function eventsRequiringTransfer(User $user): Collection
    {
        return Event::withTrashed()
            ->where(function (Builder $query) use ($user): void {
                $query->where('created_by', $user->getKey())
                    ->orWhereHas('members', function (Builder $query) use ($user): void {
                        $query->whereKey($user->getKey())
                            ->where('event_members.role', 'leader');
                    })
                    ->orWhereHas('tasks', function (Builder $query) use ($user): void {
                        $query->withTrashed()->where('created_by', $user->getKey());
                    })
                    ->orWhere(function (Builder $query) use ($user): void {
                        $query->whereHas('members', fn (Builder $members): Builder => $members->whereKey($user->getKey()))
                            ->whereDoesntHave('members', fn (Builder $members): Builder => $members->where('users.id', '!=', $user->getKey()));
                    });
            })
            ->with(['members' => fn (BelongsToMany $query): BelongsToMany => $query
                ->where('users.id', '!=', $user->getKey())
                ->orderBy('name')])
            ->orderBy('title')
            ->get();
    }

    /**
     * @param  array<string, int|string>  $successors
     */
    public function offboard(User $user, array $successors, ?string $ipAddress): void
    {
        DB::transaction(function () use ($user, $successors, $ipAddress): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $events = $this->eventsRequiringTransfer($lockedUser);

            foreach ($events as $candidateEvent) {
                $event = Event::withTrashed()
                    ->lockForUpdate()
                    ->findOrFail($candidateEvent->getKey());
                $isOwner = (int) $event->created_by === (int) $lockedUser->getKey();
                $isLeader = $event->memberships()
                    ->where('user_id', $lockedUser->getKey())
                    ->where('role', 'leader')
                    ->exists();
                $isLastMember = $event->memberships()
                    ->where('user_id', $lockedUser->getKey())
                    ->exists()
                    && ! $event->members()->where('users.id', '!=', $lockedUser->getKey())->exists();
                $authoredTasks = $event->tasks()->withTrashed()
                    ->where('created_by', $lockedUser->getKey());

                if (! $isOwner && ! $isLeader && ! $isLastMember && ! $authoredTasks->exists()) {
                    continue;
                }

                $successorId = $successors[(string) $event->getKey()] ?? null;
                $successorMembership = $successorId === null
                    ? null
                    : $event->memberships()
                        ->where('user_id', $successorId)
                        ->lockForUpdate()
                        ->first();
                $successor = $successorMembership === null
                    ? null
                    : User::query()->lockForUpdate()->find($successorId);

                if ($successorId !== null && $successor === null) {
                    throw ValidationException::withMessages([
                        'event_successors.'.$event->getKey() => 'Choose an active member of this event.',
                    ]);
                }

                if ($event->members()->where('users.id', '!=', $lockedUser->getKey())->exists() && $successor === null) {
                    throw ValidationException::withMessages([
                        'event_successors.'.$event->getKey() => 'Choose a teammate to receive this event and its task history.',
                    ]);
                }

                if ($successor === null) {
                    if (! $event->trashed()) {
                        $event->delete();
                    }

                    AuditLog::record($lockedUser, 'event.archived_for_account_deletion', $event, [
                        'title' => $event->title,
                    ], $ipAddress);

                    continue;
                }

                if ($isOwner || $isLeader) {
                    $event->memberships()
                        ->where('user_id', $successor->getKey())
                        ->update(['role' => 'leader']);
                }

                if ($isOwner) {
                    $event->forceFill(['created_by' => $successor->getKey()])->save();
                }

                $transferredTasks = $authoredTasks->update(['created_by' => $successor->getKey()]);

                AuditLog::record($lockedUser, 'event.ownership_transferred', $event, [
                    'successor_id' => $successor->getKey(),
                    'tasks_transferred' => $transferredTasks,
                ], $ipAddress);
            }

            $lockedUser->assignedTaskRecords()->withTrashed()->update(['assigned_to' => null]);
            $lockedUser->eventMemberships()->delete();
            $deletedEmail = $lockedUser->email;
            $lockedUser->notifications()->delete();
            DB::table('password_reset_tokens')
                ->whereRaw('LOWER(email) = ?', [Str::lower($deletedEmail)])
                ->delete();

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $lockedUser->getKey())
                    ->delete();
            }

            $lockedUser->forceFill([
                'name' => 'Deleted user',
                'email' => 'deleted-user-'.$lockedUser->getKey().'-'.Str::lower(Str::random(12)).'@deleted.invalid',
                'email_verified_at' => null,
                'password' => Str::random(64),
                'remember_token' => null,
                'system_role' => 'user',
                'account_status' => 'suspended',
                'suspended_at' => null,
                'suspension_reason' => null,
                'suspended_by' => null,
                'is_super_admin' => false,
                'admin_permissions' => null,
                'notification_preferences' => null,
                'last_login_at' => null,
                'last_activity_at' => null,
            ])->save();

            AuditLog::record($lockedUser, 'user.account_deleted', $lockedUser, [], $ipAddress);
            $lockedUser->delete();
        });
    }
}
