@extends('layouts.admin')

@section('title', 'Task management')
@section('heading', 'Task management')

@section('content')
    <section class="panel admin-panel">
        <form class="admin-filters admin-filters-wrap" method="GET" action="{{ route('admin.tasks.index') }}">
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search task titles" aria-label="Search tasks">
            <select name="event" aria-label="Filter by event"><option value="">All events</option>@foreach ($events as $event)<option value="{{ $event->id }}" @selected((string) ($filters['event'] ?? '') === (string) $event->id)>{{ $event->title }}</option>@endforeach</select>
            <select name="assignee" aria-label="Filter by assignee"><option value="">All assignees</option>@foreach ($users as $user)<option value="{{ $user->id }}" @selected((string) ($filters['assignee'] ?? '') === (string) $user->id)>{{ $user->name }}</option>@endforeach</select>
            <select name="creator" aria-label="Filter by creator"><option value="">All creators</option>@foreach ($users as $user)<option value="{{ $user->id }}" @selected((string) ($filters['creator'] ?? '') === (string) $user->id)>{{ $user->name }}</option>@endforeach</select>
            <select name="priority" aria-label="Filter by priority"><option value="">Any priority</option>@foreach (['low', 'medium', 'high'] as $priority)<option value="{{ $priority }}" @selected(($filters['priority'] ?? '') === $priority)>{{ str($priority)->title() }}</option>@endforeach</select>
            <select name="status" aria-label="Filter by status"><option value="">Any status</option>@foreach (['not_started', 'in_progress', 'completed'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select>
            <select name="deadline" aria-label="Filter by deadline"><option value="">Any deadline</option>@foreach (['overdue' => 'Overdue', 'today' => 'Due today', 'upcoming' => 'Upcoming', 'unassigned' => 'Unassigned'] as $value => $label)<option value="{{ $value }}" @selected(($filters['deadline'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
            <select name="trashed" aria-label="Task archive filter"><option value="0" @selected(($filters['trashed'] ?? '0') === '0')>Active tasks</option><option value="1" @selected(($filters['trashed'] ?? '') === '1')>Archived tasks</option></select>
            <button class="button button-primary button-small" type="submit">Apply</button><a class="button button-secondary button-small" href="{{ route('admin.tasks.index') }}">Reset</a>
        </form>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Task</th><th>Event</th><th>Creator</th><th>Assignee</th><th>Priority</th><th>Status</th><th>Deadline</th><th></th></tr></thead><tbody>
            @forelse ($tasks as $task)
                <tr><td><strong>{{ $task->title }}</strong><small class="table-subline">{{ \Illuminate\Support\Str::limit($task->description, 70) }}</small></td><td>{{ $task->event->title }}</td><td>{{ $task->creator->name }}</td><td>{{ $task->assignee?->name ?? 'Unassigned' }}</td><td><span class="priority-label priority-{{ $task->priority }}">{{ ucfirst($task->priority) }}</span></td><td><span class="status-badge status-{{ $task->status }}">{{ str($task->status)->replace('_', ' ')->title() }}</span></td><td class="{{ $task->due_at && $task->due_at->isPast() && $task->status !== 'completed' ? 'text-overdue' : '' }}">{{ $task->due_at?->format('M j, Y · g:i A') ?? '—' }}</td><td>
                    @if ($task->trashed() && auth()->user()->can('tasks.manage'))
                        <form method="POST" action="{{ route('admin.tasks.restore', $task) }}">@csrf<button class="button button-secondary button-small" type="submit">Restore</button></form>
                    @elseif (! $task->trashed())
                        <a class="button button-secondary button-small" href="{{ route('admin.tasks.edit', $task) }}">Edit</a>
                    @endif
                </td></tr>
            @empty
                <tr><td colspan="8" class="admin-empty">No tasks match the selected filters.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="pagination-wrap">{{ $tasks->links() }}</div>
    </section>
@endsection
