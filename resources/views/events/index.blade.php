@extends('layouts.app')

@section('title', 'My events')

@section('content')
    <div class="page-heading-row">
        <div><span class="eyebrow">YOUR SCHOOL COMMUNITY</span><h1>My events</h1><p class="page-lead">All the plans and people that bring your ideas to life.</p></div>
        <a class="button button-primary" href="{{ route('events.create') }}"><span aria-hidden="true">＋</span> Create event</a>
    </div>

    <section class="panel filter-panel">
        <form class="filter-form" action="{{ route('events.index') }}" method="GET">
            <label class="filter-search">
                <span aria-hidden="true">⌕</span>
                <span class="sr-only">Search events</span>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search events, places..." maxlength="100">
            </label>
            <label>
                <span class="sr-only">Category</span>
                <select name="category">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="sr-only">Status</span>
                <select name="status">
                    <option value="">Any status</option>
                    @foreach (['draft' => 'Draft', 'published' => 'Published', 'cancelled' => 'Cancelled', 'completed' => 'Completed'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="sr-only">Sort events</span>
                <select name="sort">
                    <option value="upcoming" @selected(($filters['sort'] ?? 'upcoming') === 'upcoming')>Date: upcoming</option>
                    <option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Recently added</option>
                    <option value="title" @selected(($filters['sort'] ?? '') === 'title')>Name: A to Z</option>
                </select>
            </label>
            <button class="button button-secondary filter-submit" type="submit">Apply</button>
            @if (count(array_filter($filters, fn ($value) => filled($value))) > 0)
                <a class="filter-reset" href="{{ route('events.index') }}">Reset</a>
            @endif
        </form>
    </section>

    @if ($events->count())
        <div class="event-card-grid">
            @foreach ($events as $event)
                @php
                    $progress = $event->active_tasks_count > 0
                        ? (int) round(($event->completed_tasks_count / $event->active_tasks_count) * 100)
                        : null;
                @endphp
                <a class="event-card" href="{{ route('events.show', $event) }}">
                    <div class="event-card-art art-{{ $loop->iteration % 4 }}">
                        <span class="event-card-category">{{ $event->category ?: 'School event' }} · {{ ucfirst($event->pivot->role) }}</span>
                        <span class="status-badge status-{{ $event->status }}">{{ str($event->status)->replace('_', ' ')->title() }}</span>
                        <span class="art-symbol" aria-hidden="true">{{ ['✦', '◈', '✳', '✧'][$loop->iteration % 4] }}</span>
                        <span class="event-card-date"><strong>{{ $event->start_at->format('d') }}</strong><small>{{ $event->start_at->format('M') }}</small></span>
                    </div>
                    <div class="event-card-content">
                        <h2>{{ $event->title }}</h2>
                        <p class="event-card-meta"><span aria-hidden="true">◷</span>{{ $event->start_at->format('D, M j · g:i A') }}</p>
                        <p class="event-card-meta"><span aria-hidden="true">⌖</span>{{ $event->location }}</p>
                        <div class="event-card-footer">
                            @if ($progress !== null)
                                <span class="progress-track"><span style="width: {{ $progress }}%"></span></span>
                                <small>{{ $progress }}%</small>
                            @else
                                <small>No tasks yet</small>
                            @endif
                            <span class="card-arrow" aria-hidden="true">↗</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="pagination-wrap">{{ $events->links() }}</div>
    @else
        <section class="panel empty-state page-empty">
            <span class="empty-illustration" aria-hidden="true">✦</span>
            <h2>{{ count(array_filter($filters, fn ($value) => filled($value))) ? 'No events match these filters' : 'Your event story starts here' }}</h2>
            <p>{{ count(array_filter($filters, fn ($value) => filled($value))) ? 'Try adjusting your search or clearing the filters.' : 'Create your first workspace and bring your team together.' }}</p>
            @if (count(array_filter($filters, fn ($value) => filled($value))))
                <a class="button button-secondary" href="{{ route('events.index') }}">Clear filters</a>
            @else
                <a class="button button-primary" href="{{ route('events.create') }}">Create your first event</a>
            @endif
        </section>
    @endif
@endsection
