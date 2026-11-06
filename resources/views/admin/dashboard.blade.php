@extends('layouts.admin')

@section('title', 'Platform overview')
@section('heading', 'Platform overview')

@section('content')
    <div class="admin-stats-grid">
        @foreach ([
            ['Registered users', $stats['users'], $stats['active_users'].' active · '.$stats['suspended_users'].' suspended · '.$stats['administrators'].' admins'],
            ['Events', $stats['events'], $stats['published_events'].' published · '.$stats['upcoming_events'].' upcoming'],
            ['Tasks', $stats['tasks'], $stats['completed_tasks'].' completed · '.$stats['in_progress_tasks'].' in progress'],
            ['Needs attention', $stats['overdue_tasks'], $stats['due_soon_tasks'].' due in 24 hours'],
        ] as [$label, $value, $detail])
            <section class="panel admin-stat-card"><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong><small>{{ $detail }}</small></section>
        @endforeach
    </div>

    <div class="admin-dashboard-grid">
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">LAST 7 DAYS</span><h2>User registrations</h2></div><a href="{{ route('admin.users.index') }}">Manage users →</a></div>
            <div class="admin-bars" aria-label="User registrations by day">
                @php($maxRegistrations = max(1, max(array_column($registrationTrend, 'count'))))
                @foreach ($registrationTrend as $day)
                    <div class="admin-bar-item"><span class="admin-bar-value">{{ $day['count'] }}</span><div class="admin-bar-track"><span style="height: {{ max(4, (int) round($day['count'] / $maxRegistrations * 100)) }}%"></span></div><small>{{ $day['date'] }}</small></div>
                @endforeach
            </div>
        </section>
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">LAST 7 DAYS</span><h2>Completed tasks</h2></div>@if (auth()->user()->can('reports.view'))<a href="{{ route('admin.reports.index') }}">Reports →</a>@endif</div>
            <div class="admin-bars" aria-label="Task completions by day">
                @php($maxCompletions = max(1, max(array_column($taskCompletionTrend, 'count'))))
                @foreach ($taskCompletionTrend as $day)
                    <div class="admin-bar-item"><span class="admin-bar-value">{{ $day['count'] }}</span><div class="admin-bar-track"><span style="height: {{ max(4, (int) round($day['count'] / $maxCompletions * 100)) }}%"></span></div><small>{{ $day['date'] }}</small></div>
                @endforeach
            </div>
        </section>
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">PLATFORM HEALTH</span><h2>Tasks by status</h2></div><a href="{{ route('admin.tasks.index') }}">View tasks →</a></div>
            <div class="admin-breakdown">
                @foreach (['not_started' => 'Pending', 'in_progress' => 'In progress', 'completed' => 'Completed'] as $status => $label)
                    <div><span class="status-dot status-dot-{{ $status }}"></span><span>{{ $label }}</span><strong>{{ number_format($taskStatuses[$status] ?? 0) }}</strong></div>
                @endforeach
            </div>
            <div class="admin-section-heading admin-events-breakdown"><div><span class="eyebrow">EVENT MIX</span><h2>Events by status</h2></div></div>
            <div class="admin-breakdown compact">
                @foreach (['published' => 'Published', 'draft' => 'Draft', 'cancelled' => 'Cancelled', 'completed' => 'Completed'] as $status => $label)
                    <div><span class="status-badge status-{{ $status }}">{{ $label }}</span><strong>{{ number_format($eventStatuses[$status] ?? 0) }}</strong></div>
                @endforeach
            </div>
        </section>
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">RECENTLY JOINED</span><h2>New users</h2></div><a href="{{ route('admin.users.index') }}">All users →</a></div>
            <div class="admin-rows">
                @forelse ($recentUsers as $user)
                    <a class="admin-list-row" href="{{ route('admin.users.show', $user) }}"><span class="avatar">{{ \Illuminate\Support\Str::of($user->name)->substr(0, 1)->upper() }}</span><span><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></span><small>{{ $user->created_at->format('M j') }}</small></a>
                @empty
                    <p class="admin-empty">No users have registered yet.</p>
                @endforelse
            </div>
        </section>
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">COMING UP</span><h2>Upcoming events</h2></div><a href="{{ route('admin.events.index') }}">All events →</a></div>
            <div class="admin-rows">
                @forelse ($upcomingEvents as $event)
                    <a class="admin-list-row" href="{{ route('admin.events.show', $event) }}"><span class="event-date-chip">{{ $event->start_at->format('M j') }}</span><span><strong>{{ $event->title }}</strong><small>By {{ $event->creator->name }}</small></span><span class="status-badge status-{{ $event->status }}">{{ str($event->status)->title() }}</span></a>
                @empty
                    <p class="admin-empty">No upcoming events.</p>
                @endforelse
            </div>
        </section>
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">RECENTLY CREATED</span><h2>New events</h2></div><a href="{{ route('admin.events.index') }}">All events →</a></div>
            <div class="admin-rows">
                @forelse ($recentEvents as $event)
                    <a class="admin-list-row" href="{{ route('admin.events.show', $event) }}"><span><strong>{{ $event->title }}</strong><small>By {{ $event->creator->name }}</small></span><small>{{ $event->created_at->diffForHumans() }}</small></a>
                @empty
                    <p class="admin-empty">No events have been created.</p>
                @endforelse
            </div>
        </section>
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">RECENTLY UPDATED</span><h2>Task activity</h2></div><a href="{{ route('admin.tasks.index') }}">All tasks →</a></div>
            <div class="admin-rows">
                @forelse ($recentTasks as $task)
                    <a class="admin-list-row" href="{{ route('admin.tasks.edit', $task) }}"><span><strong>{{ $task->title }}</strong><small>{{ $task->event->title }} · {{ $task->assignee?->name ?? 'Unassigned' }}</small></span><span class="status-badge status-{{ $task->status }}">{{ str($task->status)->replace('_', ' ')->title() }}</span></a>
                @empty
                    <p class="admin-empty">No tasks have been updated.</p>
                @endforelse
            </div>
        </section>
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">EVENT MIX</span><h2>Events by category</h2></div><a href="{{ route('admin.categories.index') }}">Categories →</a></div>
            <div class="admin-breakdown">
                @forelse ($categoryMix as $category)
                    <div><span class="category-color-swatch" style="--category-color: {{ $category->color }}"></span><span>{{ $category->name }}</span><strong>{{ number_format($category->events_count) }}</strong></div>
                @empty
                    <p class="admin-empty">No categories have been created.</p>
                @endforelse
            </div>
        </section>
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">ACCOUNT ALERTS</span><h2>Suspended accounts</h2></div><a href="{{ route('admin.users.index', ['status' => 'suspended']) }}">Review →</a></div>
            <div class="admin-rows">
                @forelse ($suspendedUsers as $user)
                    <a class="admin-list-row" href="{{ route('admin.users.show', $user) }}"><span><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></span><small>{{ $user->suspended_at?->diffForHumans() }}</small></a>
                @empty
                    <p class="admin-empty">No accounts are suspended.</p>
                @endforelse
            </div>
        </section>
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">OVERDUE</span><h2>Tasks needing attention</h2></div><a href="{{ route('admin.tasks.index', ['deadline' => 'overdue']) }}">Review →</a></div>
            <div class="admin-rows">
                @forelse ($overdueTaskList as $task)
                    <a class="admin-list-row" href="{{ route('admin.tasks.edit', $task) }}"><span class="priority-dot priority-dot-{{ $task->priority }}"></span><span><strong>{{ $task->title }}</strong><small>{{ $task->event->title }} · {{ $task->assignee?->name ?? 'Unassigned' }}</small></span><small class="text-overdue">{{ $task->due_at->diffForHumans() }}</small></a>
                @empty
                    <p class="admin-empty">No overdue tasks. Nice work.</p>
                @endforelse
            </div>
        </section>
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">ACCOUNTABILITY</span><h2>Recent activity</h2></div><a href="{{ route('admin.audit.index') }}">Audit trail →</a></div>
            <div class="admin-rows">
                @forelse ($recentActivity as $log)
                    <div class="admin-list-row"><span class="audit-mark" aria-hidden="true">•</span><span><strong>{{ str($log->action)->replace('.', ' ')->replace('_', ' ')->title() }}</strong><small>{{ $log->actor?->name ?? 'System' }} · {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</small></span><small>{{ $log->created_at->diffForHumans() }}</small></div>
                @empty
                    <p class="admin-empty">No administrative activity recorded yet.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
