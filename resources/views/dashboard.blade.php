@extends('layouts.app')

@section('title', 'Overview')

@section('content')
    <div class="welcome-row">
        <div>
            <div class="eyebrow">TUESDAY, {{ now()->format('F j, Y') }}</div>
            <h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ explode(' ', auth()->user()->name)[0] }} <span class="greeting-mark" aria-hidden="true">✦</span></h1>
            <p class="page-lead">Let's make your next event happen.</p>
        </div>
        <a class="button button-primary" href="{{ route('events.create') }}"><span aria-hidden="true">＋</span> Create event</a>
    </div>

    <section class="stat-grid" aria-label="Your event statistics">
        <article class="stat-card stat-purple">
            <div class="stat-top"><span>Events you're in</span><span class="stat-icon" aria-hidden="true">▦</span></div>
            <strong>{{ $eventCount }}</strong><small>Across your school community</small>
            <span class="stat-decoration" aria-hidden="true">▦</span>
        </article>
        <article class="stat-card stat-orange">
            <div class="stat-top"><span>Coming up</span><span class="stat-icon" aria-hidden="true">◷</span></div>
            <strong>{{ $upcomingEventCount }}</strong><small>Published events ahead</small>
            <span class="stat-decoration" aria-hidden="true">◷</span>
        </article>
        <article class="stat-card stat-blue">
            <div class="stat-top"><span>Tasks assigned</span><span class="stat-icon" aria-hidden="true">✓</span></div>
            <strong>{{ $taskCount }}</strong><small>{{ $completedTaskCount }} completed so far</small>
            <span class="stat-decoration" aria-hidden="true">✓</span>
        </article>
        <article class="stat-card stat-rose">
            <div class="stat-top"><span>Past due</span><span class="stat-icon" aria-hidden="true">!</span></div>
            <strong>{{ $overdueTaskCount }}</strong><small>{{ $overdueTaskCount === 1 ? 'Task needs' : 'Tasks need' }} your attention</small>
            <span class="stat-decoration" aria-hidden="true">!</span>
        </article>
    </section>

    <div class="dashboard-grid">
        <section class="panel upcoming-panel">
            <div class="section-heading">
                <div><span class="eyebrow">ON THE HORIZON</span><h2>Upcoming events</h2></div>
                <a class="text-link" href="{{ route('events.index') }}">All events <span aria-hidden="true">→</span></a>
            </div>
            @forelse ($events as $event)
                @php
                    $progress = $event->active_tasks_count > 0
                        ? (int) round(($event->completed_tasks_count / $event->active_tasks_count) * 100)
                        : null;
                @endphp
                <a class="event-row" href="{{ route('events.show', $event) }}">
                    <div class="event-date-tile">
                        <strong>{{ $event->start_at->format('d') }}</strong>
                        <span>{{ $event->start_at->format('M') }}</span>
                    </div>
                    <div class="event-row-main">
                        <div class="event-row-title"><strong>{{ $event->title }}</strong><span class="status-badge status-{{ $event->status }}">{{ str($event->status)->replace('_', ' ')->title() }}</span><span class="role-badge {{ $event->pivot->role === 'leader' ? 'role-leader' : '' }}">{{ ucfirst($event->pivot->role) }}</span></div>
                        <p>{{ $event->category ?: 'School event' }} <span>·</span> {{ $event->location }}</p>
                        <div class="event-progress">
                            @if ($progress !== null)
                                <span class="progress-track"><span style="width: {{ $progress }}%"></span></span>
                                <small>{{ $progress }}% tasks done</small>
                            @else
                                <small>No tasks yet</small>
                            @endif
                        </div>
                    </div>
                    <span class="event-row-arrow" aria-hidden="true">↗</span>
                </a>
            @empty
                <div class="empty-state compact-empty">
                    <span class="empty-illustration" aria-hidden="true">✦</span>
                    <h3>Your next event starts here</h3>
                    <p>Create an event workspace and invite your team to start planning together.</p>
                    <a class="button button-secondary" href="{{ route('events.create') }}">Create your first event</a>
                </div>
            @endforelse
        </section>

        <section class="panel task-panel">
            <div class="section-heading">
                <div><span class="eyebrow">YOUR NEXT MOVES</span><h2>My tasks</h2></div>
                <a class="text-link" href="{{ route('tasks.index') }}">View all <span aria-hidden="true">→</span></a>
            </div>
            @forelse ($assignedTasks as $task)
                <article class="task-list-row">
                    <span class="task-check {{ $task->status === 'completed' ? 'is-done' : '' }}" aria-hidden="true">{{ $task->status === 'completed' ? '✓' : '' }}</span>
                    <div class="task-list-content">
                        <a href="{{ route('tasks.edit', $task) }}" class="task-title">{{ $task->title }}</a>
                        <a class="task-event-link" href="{{ route('events.show', $task->event) }}">{{ $task->event->title }}</a>
                    </div>
                    <div class="task-list-meta">
                        <span class="priority-label priority-{{ $task->priority }}">{{ str($task->priority)->title() }}</span>
                        <small class="{{ $task->due_at && $task->due_at->isPast() ? 'text-overdue' : '' }}">{{ $task->due_at?->format('M j') ?? 'No due date' }}</small>
                    </div>
                </article>
            @empty
                <div class="empty-state compact-empty task-empty">
                    <span class="empty-illustration" aria-hidden="true">✓</span>
                    <h3>Nothing on your plate</h3>
                    <p>Tasks assigned to you will show up here.</p>
                    @if ($eventCount === 0)
                        <a class="text-link" href="{{ route('events.create') }}">Start by creating an event →</a>
                    @endif
                </div>
            @endforelse
        </section>
    </div>

    <section class="bottom-callout">
        <div class="callout-spark" aria-hidden="true">✳</div>
        <div><span class="eyebrow">A LITTLE MOMENTUM GOES A LONG WAY</span><h2>One clear next step can move the whole team forward.</h2></div>
        <a class="button button-light" href="{{ route('calendar') }}">See your calendar <span aria-hidden="true">→</span></a>
    </section>
@endsection
