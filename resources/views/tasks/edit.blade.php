@extends('layouts.app')

@section('title', 'Update task')

@section('content')
    <div class="page-heading-row">
        <div><a class="back-link" href="{{ route('events.show', $task->event) }}">← {{ $task->event->title }}</a><span class="eyebrow">KEEP THE MOMENTUM</span><h1>{{ $isLeader ? 'Edit task' : 'Update your task' }}</h1><p class="page-lead">{{ $task->title }}</p></div>
    </div>
    <section class="panel form-panel">
        <form method="POST" action="{{ route('tasks.update', $task) }}">
            @csrf
            @method('PATCH')
            @if ($isLeader)
                <div class="form-grid">
                    <div class="field field-span-two"><label for="title">Task title</label><input id="title" name="title" value="{{ old('title', $task->title) }}" maxlength="150" required>@error('title')<span class="field-error">{{ $message }}</span>@enderror</div>
                    <div class="field field-span-two"><label for="description">Description</label><textarea id="description" name="description" rows="4" maxlength="5000">{{ old('description', $task->description) }}</textarea>@error('description')<span class="field-error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label for="assigned_to">Assignee</label><select id="assigned_to" name="assigned_to"><option value="">Unassigned</option>@foreach ($members as $member)<option value="{{ $member->id }}" @selected((string) old('assigned_to', $task->assigned_to) === (string) $member->id)>{{ $member->name }}</option>@endforeach</select>@error('assigned_to')<span class="field-error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label for="due_at">Due date</label><input id="due_at" name="due_at" type="datetime-local" value="{{ old('due_at', $task->due_at?->format('Y-m-d\TH:i')) }}">@error('due_at')<span class="field-error">{{ $message }}</span>@enderror</div>
                    <div class="field"><label for="priority">Priority</label><select id="priority" name="priority" required>@foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'] as $value => $label)<option value="{{ $value }}" @selected(old('priority', $task->priority) === $value)>{{ $label }}</option>@endforeach</select>@error('priority')<span class="field-error">{{ $message }}</span>@enderror</div>
                </div>
            @endif
            <div class="field {{ $isLeader ? 'field-top-spaced' : '' }}">
                <label for="status">Status</label>
                <select id="status" name="status" required>@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(old('status', $task->status) === $value)>{{ $label }}</option>@endforeach</select>
                @error('status')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-actions">
                <a class="button button-quiet" href="{{ route('events.show', $task->event) }}">Cancel</a>
                <button class="button button-primary" type="submit">Save task <span aria-hidden="true">→</span></button>
            </div>
        </form>
    </section>
@endsection
