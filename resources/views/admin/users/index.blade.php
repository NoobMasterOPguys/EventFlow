@extends('layouts.admin')

@section('title', 'User management')
@section('heading', 'User management')

@section('content')
    <section class="panel admin-panel">
        <div class="admin-section-heading"><div><span class="eyebrow">ACCOUNT DIRECTORY</span><h2>Manage user access and activity</h2></div>@if (auth()->user()->can('users.create'))<a class="button button-primary button-small" href="{{ route('admin.users.create') }}">Create user</a>@endif</div>
        <form class="admin-filters" method="GET" action="{{ route('admin.users.index') }}">
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or email" aria-label="Search users">
            <select name="role" aria-label="Filter by system role"><option value="">All roles</option><option value="admin" @selected(($filters['role'] ?? '') === 'admin')>Administrator</option><option value="user" @selected(($filters['role'] ?? '') === 'user')>User</option></select>
            <select name="status" aria-label="Filter by account status"><option value="">All statuses</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>Suspended</option></select>
            <select name="sort" aria-label="Sort users"><option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest first</option><option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest first</option><option value="activity" @selected(($filters['sort'] ?? '') === 'activity')>Recent activity</option></select>
            <label>Registered from <input type="date" name="registered_from" value="{{ $filters['registered_from'] ?? '' }}"></label>
            <label>Registered to <input type="date" name="registered_to" value="{{ $filters['registered_to'] ?? '' }}"></label>
            <label>Active from <input type="date" name="activity_from" value="{{ $filters['activity_from'] ?? '' }}"></label>
            <label>Active to <input type="date" name="activity_to" value="{{ $filters['activity_to'] ?? '' }}"></label>
            <select name="trashed" aria-label="Filter deleted users"><option value="0" @selected(($filters['trashed'] ?? '0') === '0')>Current accounts</option><option value="1" @selected(($filters['trashed'] ?? '') === '1')>Deleted accounts</option></select>
            <button class="button button-primary button-small" type="submit">Apply</button><a class="button button-secondary button-small" href="{{ route('admin.users.index') }}">Reset</a>
        </form>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>User</th><th>System role</th><th>Status</th><th>Events</th><th>Memberships</th><th>Tasks</th><th>Registered</th><th>Last active</th><th></th></tr></thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td><a class="admin-user-cell" href="{{ route('admin.users.show', $user) }}"><span class="avatar">{{ \Illuminate\Support\Str::of($user->name)->substr(0, 1)->upper() }}</span><span><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></span></a></td>
                            <td><span class="admin-role-badge {{ $user->isAdministrator() ? 'is-admin' : '' }}">{{ $user->isAdministrator() ? 'Administrator' : 'User' }}</span></td>
                            <td><span class="account-status account-status-{{ $user->account_status }}">{{ $user->trashed() ? 'Deleted' : str($user->account_status)->title() }}</span></td>
                            <td>{{ $user->created_events_count }}</td><td>{{ $user->event_memberships_count }}</td><td>{{ $user->assigned_tasks_count }} <small>({{ $user->completed_tasks_count }} done)</small></td>
                            <td>{{ $user->created_at->format('M j, Y') }}</td><td>{{ $user->last_activity_at?->diffForHumans() ?? 'Never' }}</td>
                            <td><a class="text-link" href="{{ route('admin.users.show', $user) }}">Manage →</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="admin-empty">No users match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $users->links() }}</div>
    </section>
@endsection
