@extends('layouts.admin')

@section('title', 'Edit task')
@section('heading', 'Task administration')

@section('content')
    <a class="admin-back-link" href="{{ route('admin.tasks.index') }}">← All tasks</a>
    <div class="admin-detail-grid">
        <section class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">{{ $task->event->title }}</span><h2>{{ $task->title }}</h2></div></div>
            <div class="admin-profile-facts"><div><span>Created by</span><strong>{{ $task->creator->name }}</strong></div><div><span>Created</span><strong>{{ $task->created_at->format('M j, Y') }}</strong></div><div><span>Last updated</span><strong>{{ $task->updated_at->format('M j, Y · g:i A') }}</strong></div></div>
            @if (auth()->user()->can('tasks.manage'))<form method="POST" action="{{ route('admin.tasks.update', $task) }}" class="admin-event-edit">
                @csrf @method('PATCH')
                <div class="field admin-full-span"><label for="title">Title</label><input id="title" name="title" value="{{ old('title', $task->title) }}" maxlength="150" required></div>
                <div class="field admin-full-span"><label for="description">Description</label><textarea id="description" name="description" rows="4" maxlength="5000">{{ old('description', $task->description) }}</textarea></div>
                <div class="field"><label for="assigned_to">Assignee</label><select id="assigned_to" name="assigned_to"><option value="">Unassigned</option>@foreach ($members as $member)<option value="{{ $member->id }}" @selected((string) old('assigned_to', $task->assigned_to) === (string) $member->id)>{{ $member->name }}{{ $member->account_status === 'suspended' ? ' (suspended)' : '' }}</option>@endforeach</select></div>
                <div class="field"><label for="due_at">Deadline</label><input id="due_at" name="due_at" type="datetime-local" data-future-datetime data-existing-value="{{ $task->due_at?->format('Y-m-d\TH:i') }}" value="{{ old('due_at', $task->due_at?->format('Y-m-d\TH:i')) }}"></div>
                <div class="field"><label for="priority">Priority</label><select id="priority" name="priority">@foreach (['low', 'medium', 'high'] as $priority)<option value="{{ $priority }}" @selected(old('priority', $task->priority) === $priority)>{{ str($priority)->title() }}</option>@endforeach</select></div>
                <div class="field"><label for="status">Status</label><select id="status" name="status">@foreach (['not_started', 'in_progress', 'completed'] as $status)<option value="{{ $status }}" @selected(old('status', $task->status) === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
                <div class="admin-full-span"><button class="button button-primary" type="submit">Save task</button></div>
            </form>
            @else
                <p>{{ $task->description }}</p>
            @endif
        </section>
        <aside class="panel admin-panel">
            <div class="admin-section-heading"><div><span class="eyebrow">ADMIN ACTIONS</span><h2>Archive task</h2></div></div>
            <p class="admin-muted">Archiving preserves task history and can be reversed from the archived tasks filter.</p>
            @if (auth()->user()->can('tasks.manage'))<form method="POST" action="{{ route('admin.tasks.destroy', $task) }}" data-confirm="Archive this task?">@csrf @method('DELETE')<button class="button button-danger" type="submit">Archive task</button></form>@endif
        </aside>
    </div>
    <section class="panel admin-panel">
        <div class="admin-section-heading"><div><span class="eyebrow">AUDIT HISTORY</span><h2>Recent task changes</h2></div></div>
        @forelse ($activity as $record)
            <div class="admin-list-row"><span><strong>{{ str($record->action)->replace('.', ' ')->replace('_', ' ')->title() }}</strong><small>{{ $record->created_at->format('M j, Y · g:i A') }}</small></span></div>
        @empty
            <p class="admin-empty">No task changes have been recorded.</p>
        @endforelse
    </section>
@endsection
