<div>
    <!-- Be present above all else. - Naval Ravikant -->
</div>
@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
    <div class="page-heading-row">
        <div><span class="eyebrow">STAY ONE STEP AHEAD</span><h1>Notifications</h1><p class="page-lead">Task reminders, event updates, and membership changes.</p></div>
        @if ($notifications->whereNull('read_at')->isNotEmpty())
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                @method('PATCH')
                <button class="button button-secondary" type="submit">Mark all as read</button>
            </form>
        @endif
    </div>

    @if ($notifications->isNotEmpty())
        <div class="notification-list">
            @foreach ($notifications as $notification)
                @php
                    $data = $notification->data;
                    $task = $tasks->get((int) ($data['task_id'] ?? 0));
                    $event = $events->get((int) ($data['event_id'] ?? 0));
                    $isAnnouncement = ($data['notification_kind'] ?? null) === 'system.announcement';
                @endphp
                <article class="panel notification-card {{ $notification->read_at ? 'is-read' : 'is-unread' }}">
                    <span class="notification-marker" aria-hidden="true"></span>
                    <div class="notification-content">
                        <div class="notification-card-heading">
                            <strong>{{ $isAnnouncement ? ($data['title'] ?? 'System announcement') : ($data['deadline_label'] ?? 'Task update') }}</strong>
                            <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                        </div>
                        @if ($isAnnouncement)
                            <p>{{ $data['body'] ?? '' }}</p>
                        @else
                            <p><strong>{{ $data['task_title'] ?? 'Task' }}</strong> · {{ $data['event_title'] ?? 'Event' }}</p>
                        @endif
                        @if (! empty($data['due_at']))
                            <small>Deadline: {{ \Illuminate\Support\Carbon::parse($data['due_at'])->timezone(config('app.timezone'))->format('M j, Y · g:i A') }}</small>
                        @endif
                    </div>
                    <div class="notification-actions">
                        @if ($task)
                            <a class="text-link" href="{{ route('tasks.edit', $task) }}">Open task <span aria-hidden="true">→</span></a>
                        @elseif ($event)
                            <a class="text-link" href="{{ route('events.show', $event) }}">Open event <span aria-hidden="true">→</span></a>
                        @elseif (($data['notification_kind'] ?? null) === 'event.updated' || str_starts_with((string) ($data['notification_kind'] ?? ''), 'event.'))
                            <span class="notification-unavailable">Event no longer available</span>
                        @elseif (! $isAnnouncement)
                            <span class="notification-unavailable">Task no longer available</span>
                        @endif
                        @if (! $notification->read_at)
                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                @csrf
                                @method('PATCH')
                                <button class="button button-quiet button-small" type="submit">Mark read</button>
                            </form>
                        @else
                            <span class="notification-read-label">Read</span>
                        @endif
                        <form method="POST" action="{{ route('notifications.destroy', $notification->id) }}">
                            @csrf
                            @method('DELETE')
                            <button class="button button-quiet button-small text-danger" type="submit">Delete</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="pagination-wrap">{{ $notifications->links() }}</div>
    @else
        <section class="panel empty-state page-empty">
            <span class="empty-illustration" aria-hidden="true">♢</span>
            <h2>You're all caught up</h2>
            <p>New task reminders and event updates will appear here.</p>
        </section>
    @endif
@endsection
