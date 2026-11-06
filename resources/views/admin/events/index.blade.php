@extends('layouts.admin')

@section('title', 'Event management')
@section('heading', 'Event management')

@section('content')
    <section class="panel admin-panel">
        <div class="admin-section-heading"><div><span class="eyebrow">PLATFORM EVENTS</span><h2>Manage event lifecycle</h2></div>@if (auth()->user()->can('events.create'))<a class="button button-primary button-small" href="{{ route('admin.events.create') }}">Create event</a>@endif</div>
        <form class="admin-filters admin-filters-wrap" method="GET" action="{{ route('admin.events.index') }}">
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search event title" aria-label="Search events">
            <select name="category" aria-label="Filter by category"><option value="">All categories</option>@foreach ($categories as $value => $label)<option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
            <select name="status" aria-label="Filter by status"><option value="">All statuses</option>@foreach (['draft', 'published', 'cancelled', 'completed'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->title() }}</option>@endforeach</select>
            <select name="creator" aria-label="Filter by creator"><option value="">All creators</option>@foreach ($creators as $creator)<option value="{{ $creator->id }}" @selected((string) ($filters['creator'] ?? '') === (string) $creator->id)>{{ $creator->name }}</option>@endforeach</select>
            <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}" aria-label="Starts on or after">
            <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}" aria-label="Starts on or before">
            <select name="trashed" aria-label="Event archive filter"><option value="0" @selected(($filters['trashed'] ?? '0') === '0')>Active events</option><option value="1" @selected(($filters['trashed'] ?? '') === '1')>Archived events</option></select>
            <button class="button button-primary button-small" type="submit">Apply</button><a class="button button-secondary button-small" href="{{ route('admin.events.index') }}">Reset</a>
        </form>
        <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>Event</th><th>Creator</th><th>Category</th><th>Schedule</th><th>Status</th><th>Members</th><th>Tasks</th><th></th></tr></thead>
            <tbody>
                @forelse ($events as $event)
                    <tr><td><a href="{{ route('admin.events.show', $event) }}"><strong>{{ $event->title }}</strong></a><small class="table-subline">{{ $event->location }}</small></td><td>{{ $event->creator->name }}<small class="table-subline">{{ $event->creator->email }}</small></td><td>{{ $event->category ?? '—' }}</td><td>{{ $event->start_at->format('M j, Y · g:i A') }}</td><td><span class="status-badge status-{{ $event->status }}">{{ str($event->status)->title() }}</span>@if ($event->trashed())<small class="table-subline">Archived</small>@endif</td><td>{{ $event->members_count }}</td><td>{{ $event->completed_tasks_count }} / {{ $event->tasks_count }} complete</td><td>
                        @if ($event->trashed())
                            <form method="POST" action="{{ route('admin.events.restore', $event) }}">@csrf<button class="button button-secondary button-small" type="submit">Restore</button></form>
                        @else
                            @if (auth()->user()->can('events.delete'))<form method="POST" action="{{ route('admin.events.destroy', $event) }}" data-confirm="Archive this event? Its tasks and membership history will be retained.">@csrf @method('DELETE')<button class="button button-quiet button-small text-danger" type="submit">Archive</button></form>@endif
                        @endif
                    </td></tr>
                @empty
                    <tr><td colspan="8" class="admin-empty">No events match these filters.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="pagination-wrap">{{ $events->links() }}</div>
    </section>
@endsection
