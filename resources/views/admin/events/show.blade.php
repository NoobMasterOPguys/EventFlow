@extends('layouts.admin')

@section('title', $event->title)
@section('heading', 'Event administration')

@section('content')
    <a class="admin-back-link" href="{{ route('admin.events.index') }}">← All events</a>
    @if ($event->trashed())
        <div class="flash-message flash-error" role="status">This event is archived. Restore it to make it visible to members again.</div>
        @if (auth()->user()->can('events.update'))<form method="POST" action="{{ route('admin.events.restore', $event) }}">@csrf<button class="button button-primary" type="submit">Restore event</button></form>@endif
    @else
        <div class="admin-detail-grid">
            <section class="panel admin-panel">
                <div class="admin-section-heading"><div><span class="eyebrow">EVENT DETAILS</span><h2>{{ $event->title }}</h2></div><span class="status-badge status-{{ $event->status }}">{{ str($event->status)->title() }}</span></div>
                <div class="admin-profile-facts"><div><span>Created by</span><strong>@if (auth()->user()->can('users.view'))<a href="{{ route('admin.users.show', $event->creator) }}">{{ $event->creator->name }}</a>@else{{ $event->creator->name }}@endif</strong></div><div><span>Category</span><strong>{{ $event->category ?? '—' }}</strong></div><div><span>Location</span><strong>{{ $event->location }}</strong></div><div><span>Starts</span><strong>{{ $event->start_at->format('M j, Y · g:i A') }}</strong></div><div><span>Ends</span><strong>{{ $event->end_at->format('M j, Y · g:i A') }}</strong></div><div><span>Members</span><strong>{{ $event->members_count }}</strong></div><div><span>Task completion</span><strong>{{ $event->completed_tasks_count }} / {{ $event->tasks_count }}</strong></div><div><span>Overdue tasks</span><strong>{{ $overdueTaskCount }}</strong></div></div>
                @if (! auth()->user()->can('events.update'))<p>{{ $event->description }}</p>@else
                <form method="POST" action="{{ route('admin.events.update', $event) }}" class="admin-event-edit">
                    @csrf @method('PATCH')
                    <div class="field"><label for="title">Title</label><input id="title" name="title" value="{{ $event->title }}" maxlength="150" required></div>
                    <div class="field"><label for="category">Category</label><select id="category" name="category"><option value="">No category</option>@foreach ($categories as $category)<option value="{{ $category }}" @selected($event->category === $category)>{{ $category }}</option>@endforeach</select></div>
                    <div class="field"><label for="location">Location</label><input id="location" name="location" value="{{ $event->location }}" maxlength="255" required></div>
                    <div class="field"><label for="start_at">Starts</label><input id="start_at" name="start_at" type="datetime-local" data-future-datetime data-existing-value="{{ $event->start_at->format('Y-m-d\TH:i') }}" value="{{ $event->start_at->format('Y-m-d\TH:i') }}" required></div>
                    <div class="field"><label for="end_at">Ends</label><input id="end_at" name="end_at" type="datetime-local" data-future-datetime data-existing-value="{{ $event->end_at->format('Y-m-d\TH:i') }}" data-after-field="start_at" value="{{ $event->end_at->format('Y-m-d\TH:i') }}" required></div>
                    <div class="field"><label for="status">Status</label>@if ($event->status === 'cancelled' && ! auth()->user()->can('events.cancel'))<input type="hidden" name="status" value="cancelled"><input id="status" value="Cancelled (cancellation permission required)" disabled>@else<select id="status" name="status">@foreach (['draft', 'published', 'cancelled', 'completed'] as $status)@if ($status !== 'cancelled' || auth()->user()->can('events.cancel'))<option value="{{ $status }}" @selected($event->status === $status)>{{ str($status)->title() }}</option>@endif @endforeach</select>@endif</div>
                    <div class="field admin-full-span"><label for="description">Description</label><textarea id="description" name="description" rows="3" maxlength="5000">{{ $event->description }}</textarea></div>
                    <div class="admin-full-span"><button class="button button-primary" type="submit">Save event</button></div>
                </form>
                @endif
            </section>
            <section class="panel admin-panel">
                <div class="admin-section-heading"><div><span class="eyebrow">PEOPLE</span><h2>Members & leaders</h2></div></div>
                @if (auth()->user()->can('events.update'))<form method="POST" action="{{ route('admin.events.members.store', $event) }}" class="admin-add-member">@csrf<select name="user_id" required><option value="">Add an active user</option>@foreach ($eligibleMembers as $person)<option value="{{ $person->id }}">{{ $person->name }} · {{ $person->email }}</option>@endforeach</select><button class="button button-secondary button-small" type="submit">Add member</button></form>@endif
                @foreach ($members as $member)
                    <div class="admin-list-row"><span><strong>{{ $member->name }}</strong><small>{{ $member->email }}</small></span><span class="role-badge {{ $member->pivot->role === 'leader' ? 'role-leader' : '' }}">{{ ucfirst($member->pivot->role) }}</span>@if (auth()->user()->can('events.update'))<form method="POST" action="{{ route('admin.events.members.destroy', [$event, $member]) }}" data-confirm="Remove this member and unassign their tasks?">@csrf @method('DELETE')<button class="icon-button text-danger" type="submit" aria-label="Remove {{ $member->name }}">×</button></form>@endif</div>
                @endforeach
            </section>
            <section class="panel admin-panel admin-full-span">
                <div class="admin-section-heading"><div><span class="eyebrow">WORK</span><h2>Associated tasks</h2></div><a href="{{ route('admin.tasks.index', ['event' => $event->id]) }}">Manage all tasks →</a></div>
                <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Task</th><th>Creator</th><th>Assignee</th><th>Priority</th><th>Status</th><th>Deadline</th></tr></thead><tbody>
                    @forelse ($tasks as $task)<tr><td><a href="{{ route('admin.tasks.edit', $task) }}">{{ $task->title }}</a></td><td>{{ $task->creator->name }}</td><td>{{ $task->assignee?->name ?? 'Unassigned' }}</td><td>{{ ucfirst($task->priority) }}</td><td><span class="status-badge status-{{ $task->status }}">{{ str($task->status)->replace('_', ' ')->title() }}</span></td><td>{{ $task->due_at?->format('M j, Y · g:i A') ?? '—' }}</td></tr>@empty<tr><td colspan="6" class="admin-empty">No tasks are associated with this event.</td></tr>@endforelse
                </tbody></table></div>
            </section>
            <section class="panel admin-panel">
                <div class="admin-section-heading"><div><span class="eyebrow">AUDIT HISTORY</span><h2>Recent event activity</h2></div></div>
                @forelse ($activity as $record)
                    <div class="admin-list-row"><span><strong>{{ str($record->action)->replace('.', ' ')->replace('_', ' ')->title() }}</strong><small>{{ $record->created_at->format('M j, Y · g:i A') }}</small></span></div>
                @empty
                    <p class="admin-empty">No event changes have been recorded.</p>
                @endforelse
            </section>
            <section class="panel admin-panel">
                <div class="admin-section-heading"><div><span class="eyebrow">DELIVERY HISTORY</span><h2>Recent event notifications</h2></div></div>
                @forelse ($notificationHistory as $delivery)
                    <div class="admin-list-row"><span><strong>{{ $delivery->recipient_name }}</strong><small>{{ $delivery->read_at ? 'Read' : 'Unread' }}</small></span><small>{{ \Illuminate\Support\Carbon::parse($delivery->created_at)->diffForHumans() }}</small></div>
                @empty
                    <p class="admin-empty">No event notifications have been recorded.</p>
                @endforelse
            </section>
        </div>
    @endif
@endsection
