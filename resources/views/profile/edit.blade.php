@extends('layouts.app')

@section('title', 'My profile')

@section('content')
    <div class="page-heading-row">
        <div><span class="eyebrow">YOUR ACCOUNT</span><h1>Profile & security</h1><p class="page-lead">Manage the details connected to your workspace.</p></div>
    </div>
    <div class="profile-grid">
        <section class="panel form-panel profile-panel">
            <div class="form-section-heading"><span class="avatar avatar-large">{{ \Illuminate\Support\Str::of($user->name)->substr(0, 1)->upper() }}</span><div><h2>Personal details</h2><p>Your name and sign-in email.</p></div></div>
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PATCH')
                <div class="field"><label for="name">Full name</label><input id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="255" autocomplete="name" required>@error('name')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" maxlength="255" autocomplete="email" required>@error('email')<span class="field-error">{{ $message }}</span>@enderror<p class="field-help">Changing your email does not verify it. Email verification is not configured for this workspace.</p></div>
                <div class="form-actions"><span></span><button class="button button-primary" type="submit">Save profile</button></div>
            </form>
        </section>
        <section class="panel form-panel profile-panel">
            <div class="form-section-heading"><span class="security-icon" aria-hidden="true">⌑</span><div><h2>Change password</h2><p>Use at least 10 characters, with uppercase, lowercase, and a number.</p></div></div>
            <form method="POST" action="{{ route('profile.password') }}">
                @csrf
                @method('PUT')
                <div class="field"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required>@error('current_password')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="password">New password</label><input id="password" name="password" type="password" autocomplete="new-password" required>@error('password')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
                <div class="form-actions"><span></span><button class="button button-primary" type="submit">Update password</button></div>
            </form>
        </section>
        <section class="panel form-panel profile-panel">
            <div class="form-section-heading"><span class="security-icon" aria-hidden="true">♢</span><div><h2>Notifications</h2><p>Choose which in-app updates appear in your notifications.</p></div></div>
            <form method="POST" action="{{ route('profile.notifications') }}">
                @csrf
                @method('PATCH')
                @php($notificationPreferences = $user->notification_preferences ?? [])
                @foreach ([
                    'task_assignments' => 'Task assignments and reassignments',
                    'task_status_updates' => 'Task status changes',
                    'deadline_reminders' => 'Upcoming and overdue task deadlines',
                    'event_updates' => 'Event details and membership updates',
                ] as $preference => $label)
                    <label class="checkbox-row">
                        <input type="hidden" name="notification_preferences[{{ $preference }}]" value="0">
                        <input type="checkbox" name="notification_preferences[{{ $preference }}]" value="1" @checked($notificationPreferences[$preference] ?? true)>
                        <span class="checkbox-visual" aria-hidden="true"></span>
                        <span>{{ $label }}</span>
                    </label>
                    @error("notification_preferences.{$preference}")<span class="field-error">{{ $message }}</span>@enderror
                @endforeach
                <div class="form-actions"><span></span><button class="button button-primary" type="submit">Save notification preferences</button></div>
            </form>
        </section>
        <section class="panel form-panel profile-panel profile-danger-panel">
            <div class="form-section-heading"><span class="danger-icon" aria-hidden="true">!</span><div><h2>Delete account</h2><p>Remove your personal details and sign out.</p></div></div>
            <p class="danger-zone-copy">Shared event and task history is preserved. Choose a teammate to receive each event you own, lead, or have created tasks in. Events without another active teammate will be archived. Your name, email, and sign-in credentials will be removed from this account. You can register again afterward using the same email address; that creates a new account.</p>
            <form method="POST" action="{{ route('profile.destroy') }}" data-confirm="Delete your account and sign out? Shared event and task history will be transferred or archived and retained.">
                @csrf
                @method('DELETE')
                @foreach ($offboardingEvents as $event)
                    <div class="field">
                        @if ($event->members->isNotEmpty())
                            <label for="event-successor-{{ $event->id }}">
                                Transfer “{{ $event->title }}” and your task history to
                            </label>
                            <select id="event-successor-{{ $event->id }}" name="event_successors[{{ $event->id }}]" required>
                                <option value="">Choose a teammate</option>
                                @foreach ($event->members as $member)
                                    <option value="{{ $member->id }}" @selected((string) old('event_successors.'.$event->id) === (string) $member->id)>{{ $member->name }}</option>
                                @endforeach
                            </select>
                            @error('event_successors.'.$event->id)<span class="field-error">{{ $message }}</span>@enderror
                        @else
                            <p class="field-help">“{{ $event->title }}” has no other active teammate to receive it, so it will be archived and its history retained.</p>
                        @endif
                    </div>
                @endforeach
                <div class="field"><label for="account_password">Current password</label><input id="account_password" name="account_password" type="password" autocomplete="current-password" required>@error('account_password')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="form-actions"><span></span><button class="button button-danger" type="submit">Delete account</button></div>
            </form>
        </section>
    </div>
@endsection
