@extends('layouts.admin')

@section('title', 'Create event')
@section('heading', 'Create event')

@section('content')
    <a class="admin-back-link" href="{{ route('admin.events.index') }}">← All events</a>
    <section class="panel admin-panel admin-form-panel">
        <div class="admin-section-heading"><div><span class="eyebrow">NEW EVENT</span><h2>Create a platform event</h2></div></div>
        <form method="POST" action="{{ route('admin.events.store') }}" class="admin-form-grid">
            @csrf
            <div class="field"><label for="title">Title</label><input id="title" name="title" value="{{ old('title') }}" maxlength="150" required></div>
            <div class="field"><label for="category">Category</label><select id="category" name="category"><option value="">Uncategorized</option>@foreach ($categories as $value => $label)<option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="location">Location</label><input id="location" name="location" value="{{ old('location') }}" maxlength="255" required></div>
            <div class="field"><label for="status">Initial status</label><select id="status" name="status" required><option value="draft" @selected(old('status', 'draft') === 'draft')>Draft</option><option value="published" @selected(old('status') === 'published')>Published</option></select></div>
            <div class="field"><label for="start_at">Starts</label><input id="start_at" name="start_at" type="datetime-local" min="{{ $minimumScheduleAt }}" value="{{ old('start_at') }}" required></div>
            <div class="field"><label for="end_at">Ends</label><input id="end_at" name="end_at" type="datetime-local" min="{{ $minimumScheduleAt }}" value="{{ old('end_at') }}" required></div>
            <div class="field admin-form-wide"><label for="description">Description</label><textarea id="description" name="description" rows="5" maxlength="5000">{{ old('description') }}</textarea></div>
            <p class="admin-muted admin-form-wide">You will be recorded as the creator and event leader. Event times use {{ config('app.timezone') }}.</p>
            <div class="form-actions admin-form-wide"><button class="button button-primary" type="submit">Create event</button><a class="button button-secondary" href="{{ route('admin.events.index') }}">Cancel</a></div>
        </form>
    </section>
@endsection
