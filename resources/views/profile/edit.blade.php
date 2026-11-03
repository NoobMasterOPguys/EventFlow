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
    </div>
@endsection
