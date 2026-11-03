<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('eventflow-mark.svg') }}">
    <title>Reset password · EventFlow</title>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="login-body">
    <main class="login-shell">
        <section class="login-story" aria-label="About EventFlow">
            <a class="brand brand-light" href="{{ route('login') }}">
                <x-brand-mark />
                <span>eventflow<span class="brand-period">.</span></span>
            </a>
            <div class="story-content">
                <div class="story-kicker"><span></span> YOUR SCHOOL, IN SYNC</div>
                <h1>Big ideas.<br><em>Better together.</em></h1>
                <p>Bring your people, plans, and progress into one place. Your next great school event starts here.</p>
                <div class="story-orbit" aria-hidden="true">
                    <div class="orbit-ring orbit-ring-one"></div>
                    <div class="orbit-ring orbit-ring-two"></div>
                    <span class="orbit-star star-one">✦</span>
                    <span class="orbit-star star-two">✳</span>
                    <span class="orbit-star star-three">✦</span>
                    <span class="orbit-pill pill-one">Plan with purpose</span>
                    <span class="orbit-pill pill-two">Make it happen</span>
                    <x-brand-mark class="orbit-core-mark" />
                </div>
            </div>
            <div class="story-footer"><span>MADE FOR MOMENTS THAT MATTER</span><span>© {{ now()->year }} EVENTFLOW</span></div>
        </section>

        <section class="login-panel">
            <div class="login-card">
                <div class="login-mobile-brand brand">
                    <x-brand-mark /><span>eventflow<span class="brand-period">.</span></span>
                </div>
                <div class="login-heading">
                    <span class="eyebrow">SECURE YOUR ACCOUNT</span>
                    <h2>Choose a new<br>password.</h2>
                    <p>Use at least 10 characters, including uppercase, lowercase, and a number.</p>
                </div>

                @if ($errors->any())
                    <div class="login-error" role="alert">
                        <strong>We couldn't reset your password.</strong>
                        @foreach ($errors->all() as $error)<span>{{ $error }}</span>@endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('password.update') }}" class="login-form register-form" data-loading-form>
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div class="field">
                        <label for="email">Email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $email) }}" placeholder="you@school.edu" autocomplete="email" required autofocus>
                        @error('email')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="password">New password</label>
                        <div class="password-input">
                            <input id="password" name="password" type="password" placeholder="At least 10 characters" autocomplete="new-password" minlength="10" required>
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password">Show</button>
                        </div>
                        @error('password')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="password_confirmation">Confirm new password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" placeholder="Enter your new password again" autocomplete="new-password" minlength="10" required>
                    </div>
                    <button class="button button-primary button-wide login-submit" type="submit">
                        <span class="button-label">Save new password <span aria-hidden="true">→</span></span>
                        <span class="button-loading" aria-hidden="true">Updating your password…</span>
                    </button>
                </form>

                <div class="login-footer">Changed your mind? <a href="{{ route('login') }}">Back to log in</a></div>
            </div>
        </section>
    </main>
</body>
</html>
