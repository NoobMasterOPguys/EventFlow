<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Notifications\BrevoPasswordResetNotification;

#[Fillable(['name', 'email', 'password'])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    public const ADMIN_PERMISSIONS = [
        'dashboard.view',
        'users.view',
        'users.create',
        'users.update',
        'users.suspend',
        'users.delete',
        'users.change_role',
        'events.view',
        'events.create',
        'events.update',
        'events.cancel',
        'events.delete',
        'tasks.view',
        'tasks.manage',
        'categories.manage',
        'notifications.manage',
        'reports.view',
        'audit_logs.view',
        'settings.manage',
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $attributes = [
        'system_role' => 'user',
        'account_status' => 'active',
        'notification_preferences' => '{"task_assignments":true,"task_status_updates":true,"deadline_reminders":true,"event_updates":true}',
    ];

    public function createdEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    public function eventMemberships(): HasMany
    {
        return $this->hasMany(EventMember::class);
    }

    public function leaderMemberships(): HasMany
    {
        return $this->eventMemberships()->where('role', 'leader');
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_members', 'user_id', 'event_id')
            ->withPivot('id', 'role', 'joined_at')
            ->withTimestamps();
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to')
            ->whereHas('event.members', fn (Builder $query): Builder => $query->whereKey($this->getKey()));
    }

    public function assignedTaskRecords(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function suspendingAdministrator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'suspended_by')->withTrashed();
    }

    public function isAdministrator(): bool
    {
        return $this->system_role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->account_status === 'active';
    }

    public function isSuperAdministrator(): bool
    {
        return $this->isAdministrator() && (bool) $this->is_super_admin;
    }

    public function hasAdminPermission(string $permission): bool
    {
        if (! $this->isAdministrator() || ! in_array($permission, self::ADMIN_PERMISSIONS, true)) {
            return false;
        }

        return $this->isSuperAdministrator()
            || in_array($permission, $this->admin_permissions ?? self::ADMIN_PERMISSIONS, true);
    }

    public function receivesNotificationFor(string $preference): bool
    {
        return (bool) ($this->notification_preferences[$preference] ?? true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'suspended_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'notification_preferences' => 'array',
            'admin_permissions' => 'array',
        ];
    }
     /**
 * Send the password reset notification through Brevo.
 */
public function sendPasswordResetNotification($token): void
{
    $this->notify(new BrevoPasswordResetNotification($token));
}
}
