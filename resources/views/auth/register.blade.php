<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('eventflow-mark.svg') }}">
    <title>Create account · EventFlow</title>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="login-body">
    <main class="login-shell">
        <section class="login-story" aria-label="About EventFlow">
            <a class="brand brand-light" href="{{ route('register') }}">
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
                    <span class="eyebrow">YOUR NEXT CHAPTER</span>
                    <h2>Make room for<br>great things.</h2>
                    <p>Create an account and start planning together.</p>
                </div>

                @if ($errors->any())
                    <div class="login-error" role="alert">
                        <strong>We couldn't create your account.</strong>
                        @foreach ($errors->all() as $error)<span>{{ $error }}</span>@endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('register.store') }}" class="login-form register-form" data-loading-form>
                    @csrf
                    <div class="field">
                        <label for="name">Your name</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" placeholder="Jordan Rivera" autocomplete="name" maxlength="255" required autofocus>
                        @error('name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="email">Email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="you@school.edu" autocomplete="email" maxlength="255" required>
                        @error('email')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="password">Create a password</label>
                        <div class="password-input">
                            <input id="password" name="password" type="password" placeholder="At least 8 characters" autocomplete="new-password" minlength="8" required>
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password">Show</button>
                        </div>
                        @error('password')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="password_confirmation">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" placeholder="Enter your password again" autocomplete="new-password" minlength="8" required>
                    </div>
                    <button class="button button-primary button-wide login-submit" type="submit">
                        <span class="button-label">Create my account <span aria-hidden="true">→</span></span>
                        <span class="button-loading" aria-hidden="true">Creating your account…</span>
                    </button>
                </form>
                <div class="login-note"><span class="note-lock" aria-hidden="true">▣</span> Your password is stored securely.</div>
                <div class="login-footer">Already have an account? <a href="{{ route('login') }}">Log in</a></div>
            </div>
        </section>
    </main>
</body>
</html>
