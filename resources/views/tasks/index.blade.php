@extends('layouts.app')

@section('title', 'My tasks')

@section('content')
    <div class="page-heading-row">
        <div><span class="eyebrow">SMALL STEPS, BIG MOMENTUM</span><h1>My tasks</h1><p class="page-lead">A clear view of what you're moving forward.</p></div>
    </div>
    <section class="panel filter-panel">
        <form class="filter-form" action="{{ route('tasks.index') }}" method="GET">
            <label class="filter-search"><span aria-hidden="true">⌕</span><span class="sr-only">Search tasks</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search tasks or events..." maxlength="100"></label>
            <label><span class="sr-only">Task status</span><select name="status"><option value="">All statuses</option>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label><span class="sr-only">Task priority</span><select name="priority"><option value="">All priorities</option>@foreach (['high' => 'High priority', 'medium' => 'Medium priority', 'low' => 'Low priority'] as $value => $label)<option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <button class="button button-secondary filter-submit" type="submit">Apply</button>
            @if (count(array_filter($filters, fn ($value) => filled($value))) > 0)<a class="filter-reset" href="{{ route('tasks.index') }}">Reset</a>@endif
        </form>
    </section>
    @if ($tasks->count())
        <div class="panel task-table">
            @foreach ($tasks as $task)
                <article class="task-table-row">
                    <span class="task-check {{ $task->status === 'completed' ? 'is-done' : '' }}" aria-hidden="true">{{ $task->status === 'completed' ? '✓' : '' }}</span>
                    <div class="task-table-main">
                        <a class="task-title" href="{{ route('tasks.edit', $task) }}">{{ $task->title }}</a>
                        <a class="task-event-link" href="{{ route('events.show', $task->event) }}">{{ $task->event->title }}</a>
                    </div>
                    <span class="priority-label priority-{{ $task->priority }}">{{ str($task->priority)->title() }} priority</span>
                    <span class="status-badge status-{{ $task->status }}">{{ $statuses[$task->status] }}</span>
                    <span class="task-deadline {{ $task->due_at && $task->due_at->isPast() && $task->status !== 'completed' ? 'text-overdue' : '' }}">{{ $task->due_at?->format('M j, g:i A') ?? 'No due date' }}</span>
                    @if ($task->status !== 'completed')
                        <form method="POST" action="{{ route('tasks.status', $task) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="completed">
                            <button class="icon-button" type="submit" aria-label="Mark {{ $task->title }} complete">✓</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
        <div class="pagination-wrap">{{ $tasks->links() }}</div>
    @else
        <section class="panel empty-state page-empty"><span class="empty-illustration" aria-hidden="true">✓</span><h2>{{ count(array_filter($filters, fn ($value) => filled($value))) ? 'No tasks found' : 'Your task list is clear' }}</h2><p>{{ count(array_filter($filters, fn ($value) => filled($value))) ? 'Try changing your filters.' : 'When an event leader assigns you a task, you will find it here.' }}</p></section>
    @endif
@endsection
