@extends('layouts.admin')

@section('title', 'Administrator profile')
@section('heading', 'Administrator profile')

@section('content')
    <section class="panel admin-panel">
        <div class="admin-user-heading"><span class="avatar avatar-large">{{ \Illuminate\Support\Str::of($user->name)->substr(0, 1)->upper() }}</span><div><h2>{{ $user->name }}</h2><p>{{ $user->email }}</p><span class="admin-role-badge is-admin">{{ $user->isSuperAdministrator() ? 'Super administrator' : 'Administrator' }}</span></div></div>
        <div class="admin-profile-facts">
            <div><span>Account created</span><strong>{{ $user->created_at->format('M j, Y · g:i A') }}</strong></div>
            <div><span>Last login</span><strong>{{ $user->last_login_at?->format('M j, Y · g:i A') ?? 'Not recorded' }}</strong></div>
            <div><span>Last activity</span><strong>{{ $user->last_activity_at?->diffForHumans() ?? 'No recent activity' }}</strong></div>
            <div><span>Role</span><strong>{{ $user->isSuperAdministrator() ? 'Super administrator' : 'Administrator' }}</strong></div>
        </div>
        <div class="form-actions"><a class="button button-primary" href="{{ route('profile.edit') }}">Profile and password settings</a></div>
    </section>

    <section class="panel admin-panel">
        <div class="admin-section-heading"><div><span class="eyebrow">SESSIONS</span><h2>Active sessions</h2></div></div>
        @forelse ($activeSessions as $session)
            <div class="admin-list-row"><span><strong>{{ $session['current'] ? 'Current session' : 'Other session' }}</strong><small>{{ $session['user_agent'] ?: 'Unknown device' }} · {{ $session['ip_address'] ?: 'Unknown IP' }}</small></span><small>{{ \Illuminate\Support\Carbon::createFromTimestamp($session['last_activity'])->diffForHumans() }}</small></div>
        @empty
            <p class="admin-muted">This session store does not expose a database-backed session list.</p>
        @endforelse
    </section>

    <section class="panel admin-panel">
        <div class="admin-section-heading"><div><span class="eyebrow">ACCOUNTABILITY</span><h2>Recent administrator activity</h2></div></div>
        @forelse ($recentActivity as $activity)
            <div class="admin-list-row"><span><strong>{{ str($activity->action)->replace('.', ' ')->replace('_', ' ')->title() }}</strong><small>{{ class_basename($activity->auditable_type) }} #{{ $activity->auditable_id }}</small></span><small>{{ $activity->created_at->diffForHumans() }}</small></div>
        @empty
            <p class="admin-empty">No administrator actions have been recorded.</p>
        @endforelse
    </section>
@endsection
