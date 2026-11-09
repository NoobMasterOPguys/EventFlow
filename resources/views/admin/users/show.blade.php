@extends('layouts.admin')

@section('title', $user->name)
@section('heading', 'User profile')

@section('content')
    <a class="admin-back-link" href="{{ route('admin.users.index') }}">← All users</a>
    <div class="admin-user-profile">
        <section class="panel admin-panel">
            <div class="admin-user-heading"><span class="avatar avatar-large">{{ \Illuminate\Support\Str::of($user->name)->substr(0, 1)->upper() }}</span><div><h2>{{ $user->name }}</h2><p>{{ $user->email }}</p><span class="account-status account-status-{{ $user->account_status }}">{{ $user->trashed() ? 'Deleted' : str($user->account_status)->title() }}</span></div></div>
            <div class="admin-profile-facts"><div><span>Registered</span><strong>{{ $user->created_at->format('M j, Y · g:i A') }}</strong></div><div><span>Last login</span><strong>{{ $user->last_login_at?->format('M j, Y · g:i A') ?? 'Not recorded' }}</strong></div><div><span>Last activity</span><strong>{{ $user->last_activity_at?->format('M j, Y · g:i A') ?? 'No activity recorded' }}</strong></div><div><span>Events created</span><strong>{{ $user->created_events_count }}</strong></div><div><span>Event memberships</span><strong>{{ $user->event_memberships_count }}</strong></div><div><span>Assigned tasks</span><strong>{{ $user->assigned_tasks_count }}</strong></div><div><span>Completed tasks</span><strong>{{ $user->completed_tasks_count }}</strong></div></div>
        </section>
        <section class="panel admin-panel admin-actions-panel">
            <span class="eyebrow">ACCOUNT CONTROLS</span><h2>Manage access</h2>
            @if ($user->trashed())
                <p class="admin-muted">This account is soft-deleted. Restore it to re-enable sign-in after its account status is checked.</p>
                <form method="POST" action="{{ route('admin.users.restore', $user) }}" data-confirm="Restore this user account?">
                    @csrf
                    <button class="button button-primary button-small" type="submit">Restore account</button>
                </form>
            @else
            @if (auth()->user()->can('users.update'))<form method="POST" action="{{ route('admin.users.update', $user) }}" class="admin-inline-form">
                @csrf @method('PATCH')
                <div class="field"><label for="name">Name</label><input id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="255" required></div>
                <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" maxlength="255" required></div>
                <button class="button button-primary button-small" type="submit">Save user details</button>
                </form>@endif
                @if (auth()->user()->can('users.change_role'))<form method="POST" action="{{ route('admin.users.role', $user) }}" class="admin-inline-form">
                @csrf @method('PATCH')
                <div class="field"><label for="system_role">System role</label><select id="system_role" name="system_role"><option value="user" @selected($user->system_role === 'user')>User</option><option value="admin" @selected($user->system_role === 'admin')>Administrator</option></select></div>
                @if (auth()->user()->isSuperAdministrator())
                    <fieldset class="field admin-form-wide"><legend>Administrator permissions</legend><p class="admin-muted">A super administrator always retains all permissions. Other administrators receive only the selected permissions.</p>
                        @foreach ($adminPermissions as $permission)
                            <label class="admin-permission-option"><input type="checkbox" name="admin_permissions[]" value="{{ $permission }}" @checked($user->isAdministrator() ? in_array($permission, $user->admin_permissions ?? $adminPermissions, true) : $permission === 'dashboard.view')>{{ $permission }}</label>
                        @endforeach
                    </fieldset>
                @endif
                <button class="button button-secondary button-small" type="submit">Update role</button>
            </form>@endif
            @if (auth()->user()->can('users.suspend'))<form method="POST" action="{{ route('admin.users.status', $user) }}" class="admin-inline-form">
                @csrf @method('PATCH')
                <div class="field"><label for="account_status">Account status</label><select id="account_status" name="account_status"><option value="active" @selected($user->account_status === 'active')>Active</option><option value="suspended" @selected($user->account_status === 'suspended')>Suspended</option></select></div>
                @if ($user->account_status === 'suspended')<p class="admin-muted admin-form-wide">Suspension reason: {{ $user->suspension_reason ?: 'No reason recorded' }}</p>@endif
                <div class="field admin-form-wide"><label for="suspension_reason">Reason (required when suspending)</label><textarea id="suspension_reason" name="suspension_reason" maxlength="1000" rows="2"></textarea></div>
                <button class="button button-secondary button-small" type="submit">{{ $user->account_status === 'active' ? 'Suspend account' : 'Reactivate account' }}</button>
            </form>@endif
            @if (auth()->user()->can('users.update'))<form method="POST" action="{{ route('admin.users.password-reset', $user) }}" data-confirm="Send a password reset link to this user's email address?">
                @csrf
                <button class="button button-secondary button-small" type="submit">Send password reset</button>
            </form>@endif
            @if (auth()->user()->can('users.delete'))<form method="POST" action="{{ route('admin.users.destroy', $user) }}" data-confirm="Soft-delete this account? Its audit history will be retained. Accounts with linked events or tasks cannot be deleted.">
                @csrf @method('DELETE')
                <button class="button button-danger button-small" type="submit">Delete account</button>
            </form>@endif
            @endif
        </section>
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">OWNED</span><h2>Created events</h2></div><a href="{{ route('admin.events.index', ['creator' => $user->id]) }}">Browse →</a></div>
            @forelse ($createdEvents as $event)<a class="admin-list-row" href="{{ route('admin.events.show', $event) }}"><span><strong>{{ $event->title }}</strong><small>{{ $event->start_at->format('M j, Y · g:i A') }}</small></span><span class="status-badge status-{{ $event->status }}">{{ str($event->status)->title() }}</span></a>@empty<p class="admin-empty">No events created.</p>@endforelse
        </section>
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">PARTICIPATING</span><h2>Event memberships</h2></div></div>
            @forelse ($memberships as $event)<a class="admin-list-row" href="{{ route('admin.events.show', $event) }}"><span><strong>{{ $event->title }}</strong><small>Led by {{ $event->creator->name }}</small></span><span class="role-badge {{ $event->pivot->role === 'leader' ? 'role-leader' : '' }}">{{ ucfirst($event->pivot->role) }}</span></a>@empty<p class="admin-empty">No event memberships.</p>@endforelse
        </section>
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">ASSIGNED</span><h2>Assigned tasks</h2></div></div>
            @forelse ($assignedTasks as $task)<a class="admin-list-row" href="{{ route('admin.tasks.edit', $task) }}"><span><strong>{{ $task->title }}</strong><small>{{ $task->event->title }}</small></span><span class="status-badge status-{{ $task->status }}">{{ str($task->status)->replace('_', ' ')->title() }}</span></a>@empty<p class="admin-empty">No assigned tasks.</p>@endforelse
        </section>
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">CREATED</span><h2>Tasks created</h2></div></div>
            @forelse ($createdTasks as $task)<a class="admin-list-row" href="{{ route('admin.tasks.edit', $task) }}"><span><strong>{{ $task->title }}</strong><small>{{ $task->event->title }}</small></span><span class="priority-label priority-{{ $task->priority }}">{{ ucfirst($task->priority) }}</span></a>@empty<p class="admin-empty">No tasks created.</p>@endforelse
        </section>
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">IN-APP HISTORY</span><h2>Recent notifications</h2></div></div>
            @forelse ($recentNotifications as $notification)
                <div class="admin-list-row"><span><strong>{{ $notification->data['title'] ?? $notification->data['deadline_label'] ?? 'Notification' }}</strong><small>{{ $notification->data['event_title'] ?? $notification->data['body'] ?? '' }}</small></span><small>{{ $notification->created_at->diffForHumans() }}</small></div>
            @empty
                <p class="admin-empty">No notifications are recorded.</p>
            @endforelse
        </section>
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">AUDIT HISTORY</span><h2>Recent account activity</h2></div></div>
            @forelse ($recentActivity as $activity)
                <div class="admin-list-row"><span><strong>{{ str($activity->action)->replace('.', ' ')->replace('_', ' ')->title() }}</strong><small>{{ $activity->created_at->format('M j, Y · g:i A') }}</small></span></div>
            @empty
                <p class="admin-empty">No account activity is recorded.</p>
            @endforelse
        </section>
    </div>
@endsection
