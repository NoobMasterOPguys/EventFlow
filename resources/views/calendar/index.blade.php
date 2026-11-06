@extends('layouts.app')

@section('title', 'Calendar')

@section('content')
    <div class="page-heading-row calendar-heading">
        <div><span class="eyebrow">YOUR TIME, WELL SPENT</span><h1>Calendar</h1><p class="page-lead">Events and deadlines, all in one place.</p></div>
        <div class="calendar-controls">
            <a class="button button-secondary button-small" href="{{ route('calendar', ['month' => $previousMonth]) }}" aria-label="Previous month">←</a>
            <a class="button button-quiet button-small" href="{{ route('calendar') }}">Today</a>
            <a class="button button-secondary button-small" href="{{ route('calendar', ['month' => $nextMonth]) }}" aria-label="Next month">→</a>
            <strong>{{ $month->format('F Y') }}</strong>
        </div>
    </div>
    <section class="panel google-calendar-panel" aria-labelledby="google-calendar-heading">
        <div class="google-calendar-copy">
            <span class="eyebrow">KEEP YOUR SCHEDULE IN SYNC</span>
            <h2 id="google-calendar-heading">Google Calendar</h2>
            @if (! $googleCalendarConfigured)
                <p>Connection is not configured yet. Add <code>GOOGLE_CLIENT_ID</code> and <code>GOOGLE_CLIENT_SECRET</code> to the application environment to enable OAuth.</p>
            @elseif ($googleCalendarConnected)
                <p><span class="connection-indicator is-connected" aria-hidden="true"></span>Connected. Sync exports events you can access and updates or removes them on the next sync.</p>
            @else
                <p><span class="connection-indicator" aria-hidden="true"></span>Not connected. EventFlow only exports events after you authorize Google Calendar.</p>
            @endif
        </div>
        @if ($googleCalendarConfigured && $googleCalendarConnected)
            <div class="google-calendar-actions">
                <form method="POST" action="{{ route('google-calendar.sync') }}">
                    @csrf
                    <button class="button button-primary" type="submit">Sync events</button>
                </form>
                <form method="POST" action="{{ route('google-calendar.disconnect') }}">
                    @csrf
                    @method('DELETE')
                    <button class="button button-quiet" type="submit">Disconnect</button>
                </form>
            </div>
        @elseif ($googleCalendarConfigured)
            <form method="GET" action="{{ route('google-calendar.connect') }}" data-loading-form>
                <button class="button button-primary" type="submit">
                    <span class="button-label">Connect Google Calendar</span>
                    <span class="button-loading" aria-hidden="true">Connecting…</span>
                </button>
            </form>
        @endif
    </section>
    <section class="panel calendar-panel">
        @unless ($hasCalendarItems)
            <div class="calendar-empty"><span class="empty-illustration" aria-hidden="true">✦</span><div><strong>A little breathing room.</strong><p>Events and assigned task deadlines will appear here as your plans take shape.</p></div><a class="text-link" href="{{ route('events.create') }}">Create an event →</a></div>
        @endunless
        <div class="calendar-weekdays" aria-hidden="true">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)<span>{{ $weekday }}</span>@endforeach
        </div>
        <div class="calendar-grid">
            @foreach ($days as $day)
                <section class="calendar-day {{ $day['inMonth'] ? '' : 'is-outside' }} {{ $day['date']->isToday() ? 'is-today' : '' }}" aria-label="{{ $day['date']->format('l, F j') }}">
                    <div class="calendar-day-number"><span>{{ $day['date']->format('j') }}</span>@if ($day['date']->isToday())<small>Today</small>@endif</div>
                    <div class="calendar-day-events">
                        @foreach ($day['events'] as $event)
                            <a class="calendar-item calendar-event" href="{{ route('events.show', $event) }}" title="{{ $event->title }} · {{ $event->category ?: 'Event' }} · {{ str($event->status)->title() }} · {{ $event->start_at->format('g:i A') }}" aria-label="{{ $event->title }}, {{ $event->category ?: 'Event' }}, {{ str($event->status)->title() }}, {{ $event->start_at->format('g:i A') }}"><span class="calendar-item-time">{{ $event->start_at->format('g:i') }}</span><span class="calendar-item-status">{{ $event->category ?: 'Event' }} · </span>{{ \Illuminate\Support\Str::limit($event->title, 21) }}</a>
                        @endforeach
                        @foreach ($day['tasks'] as $task)
                            <a class="calendar-item calendar-task priority-calendar-{{ $task->priority }}" href="{{ route('tasks.edit', $task) }}" title="{{ str($task->priority)->title() }} priority task due: {{ $task->title }}" aria-label="{{ str($task->priority)->title() }} priority task due: {{ $task->title }}"><span class="calendar-item-time">Task</span><span class="calendar-item-priority">{{ str($task->priority)->title() }} · </span>{{ \Illuminate\Support\Str::limit($task->title, 21) }}</a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
        <div class="calendar-legend"><span><i class="legend-event"></i>Events</span><span><i class="legend-task"></i>Task deadlines</span></div>
    </section>
    @if ($hasCalendarItems)
        <section class="panel calendar-agenda">
            <div class="section-heading">
                <div><span class="eyebrow">THE MONTH, AT A GLANCE</span><h2>Agenda</h2></div>
            </div>
            <div class="agenda-list">
                @foreach ($days as $day)
                    @if ($day['inMonth'] && ($day['events']->isNotEmpty() || $day['tasks']->isNotEmpty()))
                        <div class="agenda-day">
                            <time class="agenda-date" datetime="{{ $day['date']->toDateString() }}"><strong>{{ $day['date']->format('d') }}</strong><span>{{ $day['date']->format('D') }}</span></time>
                            <div class="agenda-day-items">
                                @foreach ($day['events'] as $event)
                                    <a class="agenda-entry" href="{{ route('events.show', $event) }}">
                                        <span class="agenda-marker agenda-marker-event" aria-hidden="true"></span>
                                        <span class="agenda-entry-main"><strong>{{ $event->title }}</strong><small>Event · {{ $event->start_at->format('g:i A') }} – {{ $event->end_at->format('g:i A') }} · {{ str($event->status)->title() }}</small></span>
                                        <span class="status-badge status-{{ $event->status }}">{{ str($event->status)->title() }}</span>
                                    </a>
                                @endforeach
                                @foreach ($day['tasks'] as $task)
                                    <a class="agenda-entry" href="{{ route('tasks.edit', $task) }}">
                                        <span class="agenda-marker agenda-marker-task priority-calendar-{{ $task->priority }}" aria-hidden="true"></span>
                                        <span class="agenda-entry-main"><strong>{{ $task->title }}</strong><small>Task · {{ $task->event->title }} · {{ str($task->priority)->title() }} priority</small></span>
                                        <span class="priority-label priority-{{ $task->priority }}">{{ str($task->priority)->title() }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
    @endif
    <p class="calendar-note">Event times are shown in your application's configured timezone.</p>
@endsection
