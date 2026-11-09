@extends('layouts.admin')

@section('title', 'System settings')
@section('heading', 'System settings')

@section('content')
    <section class="panel admin-panel">
        <div class="admin-section-heading"><div><span class="eyebrow">APPLICATION</span><h2>General configuration</h2></div></div>
        <p class="admin-muted">Security-critical credentials remain in environment configuration and cannot be viewed or changed here.</p>
        <form method="POST" action="{{ route('admin.settings.update') }}" class="admin-form-grid">
            @csrf @method('PATCH')
            <div class="field"><label for="application_name">Application name</label><input id="application_name" name="application_name" maxlength="80" value="{{ old('application_name', $settings['application_name']) }}" required></div>
            <div class="field"><label for="timezone">Default timezone</label><select id="timezone" name="timezone" required>@foreach ($timezones as $timezone)<option value="{{ $timezone }}" @selected(old('timezone', $settings['timezone']) === $timezone)>{{ $timezone }}</option>@endforeach</select></div>
            <div class="field"><label for="deadline_reminder_hours">Deadline reminder lead time (hours)</label><input id="deadline_reminder_hours" name="deadline_reminder_hours" type="number" min="1" max="168" value="{{ old('deadline_reminder_hours', $settings['deadline_reminder_hours']) }}" required></div>
            <div class="field"><label for="registration_enabled">Public registration</label><select id="registration_enabled" name="registration_enabled"><option value="1" @selected((bool) old('registration_enabled', $settings['registration_enabled']))>Enabled</option><option value="0" @selected(! (bool) old('registration_enabled', $settings['registration_enabled']))>Disabled</option></select></div>
            <div class="form-actions admin-form-wide"><button class="button button-primary" type="submit">Save settings</button></div>
        </form>
    </section>
@endsection
