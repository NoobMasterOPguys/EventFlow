@extends('layouts.app')

@section('title', $event->title)

@section('content')
    @php
        $progress = $event->active_tasks_count > 0
            ? (int) round(($event->completed_tasks_count / $event->active_tasks_count) * 100)
            : null;
    @endphp
    <div class="workspace-hero">
        <div class="workspace-hero-top">
            <a class="back-link back-link-light" href="{{ route('events.index') }}">← My events</a>
            <span class="status-badge status-{{ $event->status }}">{{ str($event->status)->replace('_', ' ')->title() }}</span>
        </div>
        <div class="workspace-hero-content">
            <div class="workspace-title-block">
                <span class="eyebrow">{{ $event->category ?: 'EVENT WORKSPACE' }} <span class="hero-dot">·</span> {{ $event->start_at->format('M j, Y') }}</span>
                <h1>{{ $event->title }}</h1>
                <p>{{ $event->description ?: 'A shared space to keep your plans, people, and progress moving.' }}</p>
            </div>
            <div class="workspace-hero-actions">
                @if ($isLeader)
                    <a class="button button-light" href="{{ route('events.edit', $event) }}">Edit details</a>
                @endif
                <span class="hero-date-pill"><span aria-hidden="true">◷</span>{{ $event->start_at->format('D, M j · g:i A') }} – {{ $event->start_at->isSameDay($event->end_at) ? $event->end_at->format('g:i A') : $event->end_at->format('M j · g:i A') }}</span>
            </div>
        </div>
        <div class="workspace-hero-bottom">
            <div class="hero-detail"><span aria-hidden="true">⌖</span><span>{{ $event->location }}</span></div>
            <div class="hero-detail"><span aria-hidden="true">♧</span><span>Created by {{ $event->creator->name }}</span></div>
            <div class="hero-detail"><span aria-hidden="true">♙</span><span>{{ $event->members->count() }} {{ \Illuminate\Support\Str::plural('person', $event->members->count()) }}</span></div>
            @if ($progress !== null)
                <div class="hero-progress"><span class="progress-track"><span style="width: {{ $progress }}%"></span></span><small>{{ $progress }}% complete</small></div>
            @else
                <div class="hero-progress"><small>No tasks yet</small></div>
            @endif
        </div>
    </div>

    @if ($isLeader)
        <form class="status-control" method="POST" action="{{ route('events.status', $event) }}" data-confirm="Update the event status for everyone in this workspace?">
            @csrf
            <label for="event-status">Event status</label>
            <select id="event-status" name="status">
                @foreach (['draft' => 'Draft', 'published' => 'Published', 'cancelled' => 'Cancelled', 'completed' => 'Completed'] as $value => $label)
                    <option value="{{ $value }}" @selected($event->status === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="button button-secondary button-small" type="submit">Update status</button>
        </form>
    @endif

    <div class="workspace-grid">
        <section class="panel board-panel">
            <div class="section-heading board-heading">
                <div><span class="eyebrow">MAKE IT HAPPEN</span><h2>Task board <span class="count-pill">{{ $event->active_tasks_count }}</span></h2></div>
                @if ($isLeader)
                    <a class="button button-primary button-small" href="#new-task"><span aria-hidden="true">＋</span> Add task</a>
                @endif
            </div>
            <form class="board-filters" action="{{ route('events.show', $event) }}" method="GET">
                <label><span class="sr-only">Search tasks</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Find a task..." maxlength="100"></label>
                <label><span class="sr-only">Filter by status</span><select name="status"><option value="">All statuses</option>@foreach ($taskStatuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label><span class="sr-only">Filter by priority</span><select name="priority"><option value="">All priorities</option>@foreach (['high' => 'High priority', 'medium' => 'Medium priority', 'low' => 'Low priority'] as $value => $label)<option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label><span class="sr-only">Filter by assignee</span><select name="assignee"><option value="">All people</option>@foreach ($event->members as $member)<option value="{{ $member->id }}" @selected((string) ($filters['assignee'] ?? '') === (string) $member->id)>{{ $member->name }}</option>@endforeach</select></label>
                <label class="board-unassigned"><input type="checkbox" name="unassigned" value="1" @checked($filters['unassigned'] ?? false)> Unassigned</label>
                <button class="button button-secondary button-small" type="submit">Filter</button>
                @if (count(array_filter($filters, fn ($value) => filled($value))) > 0)<a class="filter-reset" href="{{ route('events.show', $event) }}">Reset</a>@endif
            </form>
            @if ($tasks->isEmpty())
                <div class="empty-state board-empty"><span class="empty-illustration" aria-hidden="true">✦</span><h3>No tasks in this view</h3><p>{{ $event->active_tasks_count === 0 ? 'Add a first task to turn your plans into progress.' : 'Try adjusting the filters above.' }}</p></div>
            @else
                <div class="kanban-board">
                    @foreach ($taskStatuses as $status => $label)
                        <section class="kanban-column">
                            <div class="kanban-column-heading"><span class="column-indicator indicator-{{ $status }}"></span><h3>{{ $label }}</h3><span class="column-count">{{ $tasks->where('status', $status)->count() }}</span></div>
                            <div class="kanban-cards">
                                @forelse ($tasks->where('status', $status) as $task)
                                    <article class="task-card priority-edge-{{ $task->priority }}">
                                        <div class="task-card-top"><span class="priority-label priority-{{ $task->priority }}">{{ str($task->priority)->title() }}</span>@if ($task->due_at && $task->due_at->isPast() && $task->status !== 'completed')<span class="task-overdue">Overdue</span>@endif</div>
                                        <h4>{{ $task->title }}</h4>
                                        @if ($task->description)<p class="task-card-description">{{ \Illuminate\Support\Str::limit($task->description, 100) }}</p>@endif
                                        <div class="task-card-bottom">
                                            <span class="task-assignee">{{ $task->assignee ? \Illuminate\Support\Str::of($task->assignee->name)->substr(0, 1)->upper() : '—' }}<span>{{ $task->assignee?->name ?? 'Unassigned' }}</span></span>
                                            <span class="task-card-due">{{ $task->due_at?->format('M j') ?? 'No due date' }}</span>
                                        </div>
                                        <div class="task-card-actions">
                                            @if ($isLeader || $task->assigned_to === auth()->id())
                                                <a class="text-link" href="{{ route('tasks.edit', $task) }}">{{ $isLeader ? 'Edit' : 'Update' }}</a>
                                            @endif
                                            @if ($isLeader)
                                                <form method="POST" action="{{ route('tasks.destroy', $task) }}" data-confirm="Archive this task? Its history will remain in the database.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="text-link text-danger" type="submit">Archive</button>
                                                </form>
                                            @elseif ($task->assigned_to === auth()->id() && $task->status !== 'completed')
                                                <form method="POST" action="{{ route('tasks.status', $task) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="completed">
                                                    <button class="text-link" type="submit">Mark done</button>
                                                </form>
                                            @endif
                                        </div>
                                    </article>
                                @empty
                                    <p class="column-empty">No tasks here yet.</p>
                                @endforelse
                            </div>
                        </section>
                    @endforeach
                </div>
                @if ($tasks->count() === 100)
                    <p class="list-limit-note">Showing the first 100 tasks. Use filters to narrow this board.</p>
                @endif
            @endif
        </section>

        <aside class="workspace-aside">
            @if ($isLeader)
                <section class="panel side-panel" id="new-task">
                    <div class="side-panel-heading"><span class="eyebrow">NEXT ACTION</span><h2>Add a task</h2><p>Break the plan into a clear next step.</p></div>
                    <form class="stacked-form" method="POST" action="{{ route('events.tasks.store', $event) }}">
                        @csrf
                        <div class="field"><label for="task-title">Task name</label><input id="task-title" name="title" value="{{ old('title') }}" maxlength="150" placeholder="What needs doing?" required>@error('title')<span class="field-error">{{ $message }}</span>@enderror</div>
                        <div class="field"><label for="task-assignee">Assign to</label><select id="task-assignee" name="assigned_to"><option value="">Leave unassigned</option>@foreach ($event->members as $member)<option value="{{ $member->id }}" @selected((string) old('assigned_to') === (string) $member->id)>{{ $member->name }}</option>@endforeach</select>@error('assigned_to')<span class="field-error">{{ $message }}</span>@enderror</div>
                        <div class="field"><label for="task-due">Due date</label><input id="task-due" name="due_at" type="datetime-local" value="{{ old('due_at') }}">@error('due_at')<span class="field-error">{{ $message }}</span>@enderror</div>
                        <div class="field"><label for="task-priority">Priority</label><select id="task-priority" name="priority" required>@foreach (['medium' => 'Medium', 'low' => 'Low', 'high' => 'High'] as $value => $label)<option value="{{ $value }}" @selected(old('priority', 'medium') === $value)>{{ $label }}</option>@endforeach</select>@error('priority')<span class="field-error">{{ $message }}</span>@enderror</div>
                        <div class="field"><label for="task-description">Details <span class="optional-label">Optional</span></label><textarea id="task-description" name="description" rows="3" maxlength="5000">{{ old('description') }}</textarea>@error('description')<span class="field-error">{{ $message }}</span>@enderror</div>
                        <input type="hidden" name="status" value="not_started">
                        <button class="button button-primary button-wide" type="submit">Add to task board <span aria-hidden="true">→</span></button>
                    </form>
                </section>
            @endif

            <section class="panel side-panel team-panel">
                <div class="section-heading side-panel-heading"><div><span class="eyebrow">THE PEOPLE BEHIND IT</span><h2>Team <span class="count-pill">{{ $event->members->count() }}</span></h2></div></div>
                @if ($isLeader && $availableUsers->isNotEmpty())
                    <form class="add-member-form" method="POST" action="{{ route('events.members.store', $event) }}">
                        @csrf
                        <label class="sr-only" for="new-member">Choose a person with an account</label>
                        <select id="new-member" name="user_id" required><option value="">Choose someone to invite</option>@foreach ($availableUsers as $person)<option value="{{ $person->id }}">{{ $person->name }} · {{ $person->email }}</option>@endforeach</select>
                        <button class="button button-secondary button-small" type="submit">Add</button>
                        @error('user_id')<span class="field-error">{{ $message }}</span>@enderror
                    </form>
                @elseif ($isLeader)
                    <p class="helper-text">Everyone with an account is already in this event.</p>
                @endif
                <div class="member-list">
                    @foreach ($event->members as $member)
                        <div class="member-row">
                            <span class="avatar avatar-small">{{ \Illuminate\Support\Str::of($member->name)->substr(0, 1)->upper() }}</span>
                            <div class="member-info"><strong>{{ $member->name }}</strong><small>{{ $member->completed_tasks_count }} of {{ $member->event_tasks_count }} tasks complete</small></div>
                            <span class="role-badge {{ $member->pivot->role === 'leader' ? 'role-leader' : '' }}">{{ ucfirst($member->pivot->role) }}</span>
                            @if ($isLeader && $member->pivot->role === 'member')
                                <form method="POST" action="{{ route('events.members.destroy', [$event, $member]) }}" data-confirm="Remove {{ $member->name }}? Their assigned tasks will become unassigned, and task history will be kept.">
                                    @csrf
                                    @method('DELETE')
                                    <button class="icon-button icon-button-subtle" type="submit" aria-label="Remove {{ $member->name }}">×</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="team-footnote"><span aria-hidden="true">✦</span> {{ $event->members->sum('completed_tasks_count') }} tasks completed by this team</div>
            </section>
        </aside>
    </div>
@endsection
