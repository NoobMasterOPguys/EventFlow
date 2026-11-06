<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['admin', 'user'])],
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'activity'])],
            'registered_from' => ['nullable', 'date'],
            'registered_to' => ['nullable', 'date', 'after_or_equal:registered_from'],
            'activity_from' => ['nullable', 'date'],
            'activity_to' => ['nullable', 'date', 'after_or_equal:activity_from'],
            'trashed' => ['nullable', Rule::in(['0', '1'])],
        ]);

        $usersQuery = User::query();
        if (($filters['trashed'] ?? '0') === '1') {
            $usersQuery->onlyTrashed();
        }

        $users = $usersQuery
            ->withCount([
                'createdEvents',
                'eventMemberships',
                'assignedTaskRecords as assigned_tasks_count',
                'assignedTaskRecords as completed_tasks_count' => fn (Builder $query): Builder => $query->where('status', 'completed'),
            ])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['role'] ?? null, fn (Builder $query, string $role): Builder => $query->where('system_role', $role))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('account_status', $status))
            ->when($filters['registered_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
            ->when($filters['registered_to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))
            ->when($filters['activity_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('last_activity_at', '>=', $date))
            ->when($filters['activity_to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('last_activity_at', '<=', $date));

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $users->orderBy('created_at')->orderBy('id'),
            'activity' => $users->orderByDesc('last_activity_at')->orderByDesc('id'),
            default => $users->latest()->orderByDesc('id'),
        };

        return view('admin.users.index', [
            'users' => $users->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $user = DB::transaction(function () use ($request, $validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make(Str::random(64)),
            ]);

            AuditLog::record($request->user(), 'user.created', $user, [
                'name' => $user->name,
                'email' => $user->email,
            ], $request->ip());

            return $user;
        });

        $resetStatus = Password::sendResetLink(['email' => $user->email]);

        if ($resetStatus !== Password::RESET_LINK_SENT) {
            return redirect()->route('admin.users.show', $user)
                ->with('error', 'The account was created, but the password setup email could not be sent. Use Send password reset to retry.');
        }

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'User account created. A password setup link was sent to the user.');
    }

    public function show(User $user): View
    {
        $user->loadCount([
            'createdEvents',
            'eventMemberships',
            'assignedTaskRecords as assigned_tasks_count',
            'assignedTaskRecords as completed_tasks_count' => fn (Builder $query): Builder => $query->where('status', 'completed'),
        ]);

        return view('admin.users.show', [
            'user' => $user,
            'adminPermissions' => User::ADMIN_PERMISSIONS,
            'createdEvents' => $user->createdEvents()->latest()->limit(8)->get(),
            'memberships' => $user->events()->with('creator')->orderByDesc('events.start_at')->limit(8)->get(),
            'assignedTasks' => $user->assignedTasks()->with('event')->latest()->limit(8)->get(),
            'createdTasks' => $user->createdTasks()->with('event')->latest()->limit(8)->get(),
            'recentNotifications' => $user->notifications()->latest()->limit(8)->get(),
            'recentActivity' => AuditLog::query()
                ->where('auditable_type', User::class)
                ->where('auditable_id', $user->getKey())
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }

    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->with('error', 'The password reset link could not be sent. Please try again later.');
        }

        AuditLog::record($request->user(), 'user.password_reset_requested', $user, [], $request->ip());

        return back()->with('success', 'A password reset link was sent to the user.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
        ]);

        $before = $user->only(['name', 'email']);
        $emailChanged = $user->email !== $validated['email'];
        $user->fill($validated)->save();
        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null])->save();
        }

        AuditLog::record($request->user(), 'user.profile_updated', $user, [
            'before' => $before,
            'after' => $user->only(['name', 'email']),
        ], $request->ip());

        return back()->with('success', $emailChanged
            ? 'User details updated. The new email address requires verification.'
            : 'User details updated.');
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'system_role' => ['required', Rule::in(['user', 'admin'])],
            'admin_permissions' => ['sometimes', 'array'],
            'admin_permissions.*' => ['required', Rule::in(User::ADMIN_PERMISSIONS)],
        ]);
        if (
            $validated['system_role'] === 'admin'
            && isset($validated['admin_permissions'])
            && ! in_array('dashboard.view', $validated['admin_permissions'], true)
        ) {
            throw ValidationException::withMessages([
                'admin_permissions' => 'Administrator access must include dashboard.view.',
            ]);
        }
        $actor = $request->user();
        abort_if(array_key_exists('admin_permissions', $validated) && ! $actor->isSuperAdministrator(), 403);

        if ($actor->is($user) && $validated['system_role'] !== $user->system_role) {
            return back()->with('error', 'You cannot change your own administrator role.');
        }

        $updated = DB::transaction(function () use ($actor, $request, $user, $validated): bool {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (
                $lockedUser->system_role === 'admin'
                && $validated['system_role'] !== 'admin'
                && $lockedUser->account_status === 'active'
                && (
                    $this->activeAdministratorCount() <= 1
                    || ($lockedUser->isSuperAdministrator() && $this->activeSuperAdministratorCount() <= 1)
                    || ($lockedUser->isSuperAdministrator() && ! $actor->isSuperAdministrator())
                )
            ) {
                return false;
            }

            $oldRole = $lockedUser->system_role;
            $permissions = match ($validated['system_role']) {
                'user' => null,
                'admin' => $validated['admin_permissions']
                    ?? ($lockedUser->isAdministrator() ? $lockedUser->admin_permissions : ['dashboard.view']),
            };
            $lockedUser->forceFill([
                'system_role' => $validated['system_role'],
                'admin_permissions' => $permissions,
            ])->save();
            AuditLog::record($actor, 'user.role_changed', $lockedUser, [
                'from' => $oldRole,
                'to' => $validated['system_role'],
                'permissions' => $permissions,
            ], $request->ip());

            return true;
        });

        if (! $updated) {
            return back()->with('error', 'The last active administrator cannot be demoted.');
        }

        return back()->with('success', 'System role updated.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'account_status' => ['required', Rule::in(['active', 'suspended'])],
            'suspension_reason' => ['required_if:account_status,suspended', 'nullable', 'string', 'max:1000'],
        ]);
        $actor = $request->user();

        if ($actor->is($user) && $validated['account_status'] !== $user->account_status) {
            return back()->with('error', 'You cannot suspend your own account.');
        }

        $updated = DB::transaction(function () use ($actor, $request, $user, $validated): bool {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (
                $lockedUser->system_role === 'admin'
                && $lockedUser->account_status === 'active'
                && $validated['account_status'] === 'suspended'
                && (
                    $this->activeAdministratorCount() <= 1
                    || ($lockedUser->isSuperAdministrator() && $this->activeSuperAdministratorCount() <= 1)
                    || ($lockedUser->isSuperAdministrator() && ! $actor->isSuperAdministrator())
                )
            ) {
                return false;
            }

            $oldStatus = $lockedUser->account_status;
            $lockedUser->forceFill([
                'account_status' => $validated['account_status'],
                'suspended_at' => $validated['account_status'] === 'suspended' ? now() : null,
                'suspension_reason' => $validated['account_status'] === 'suspended'
                    ? trim($validated['suspension_reason'] ?? '')
                    : null,
                'suspended_by' => $validated['account_status'] === 'suspended' ? $actor->getKey() : null,
            ])->save();
            AuditLog::record($actor, 'user.status_changed', $lockedUser, [
                'from' => $oldStatus,
                'to' => $validated['account_status'],
                'reason' => $validated['suspension_reason'] ?? null,
            ], $request->ip());

            if ($validated['account_status'] === 'suspended') {
                $lockedUser->forceFill(['remember_token' => Str::random(60)])->save();

                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))
                        ->where('user_id', $lockedUser->getKey())
                        ->delete();
                }
            }

            return true;
        });

        if (! $updated) {
            return back()->with('error', 'The last active administrator cannot be suspended.');
        }

        return back()->with('success', $validated['account_status'] === 'suspended'
            ? 'Account suspended and active sessions invalidated.'
            : 'Account reactivated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->with('error', 'You cannot delete your own account from the administrator panel.');
        }

        $deleted = DB::transaction(function () use ($request, $user): string {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (
                $lockedUser->system_role === 'admin'
                && $lockedUser->account_status === 'active'
                && (
                    $this->activeAdministratorCount() <= 1
                    || ($lockedUser->isSuperAdministrator() && $this->activeSuperAdministratorCount() <= 1)
                    || ($lockedUser->isSuperAdministrator() && ! $request->user()->isSuperAdministrator())
                )
            ) {
                return 'last_admin';
            }

            if (
                $lockedUser->createdEvents()->withTrashed()->exists()
                || $lockedUser->createdTasks()->withTrashed()->exists()
                || $lockedUser->eventMemberships()->exists()
            ) {
                return 'linked_records';
            }

            AuditLog::record($request->user(), 'user.deleted', $lockedUser, [
                'name' => $lockedUser->name,
                'email' => $lockedUser->email,
            ], $request->ip());
            $lockedUser->delete();

            return 'deleted';
        });

        return match ($deleted) {
            'last_admin' => back()->with('error', 'The last active administrator cannot be deleted.'),
            'linked_records' => back()->with('error', 'This account has linked events or tasks and cannot be safely deleted.'),
            default => redirect()->route('admin.users.index')->with('success', 'User account deleted.'),
        };
    }

    public function restore(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->trashed(), 404);
        $user->restore();

        AuditLog::record($request->user(), 'user.restored', $user, [
            'name' => $user->name,
            'email' => $user->email,
        ], $request->ip());

        return back()->with('success', 'User account restored.');
    }

    private function activeAdministratorCount(): int
    {
        return User::query()
            ->where('system_role', 'admin')
            ->where('account_status', 'active')
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id'])
            ->count();
    }

    private function activeSuperAdministratorCount(): int
    {
        return User::query()
            ->where('system_role', 'admin')
            ->where('account_status', 'active')
            ->where('is_super_admin', true)
            ->lockForUpdate()
            ->count();
    }
}
