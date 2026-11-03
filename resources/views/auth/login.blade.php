<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('eventflow-mark.svg') }}">
    <title>Sign in · EventFlow</title>
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
                    <span class="eyebrow">WELCOME BACK</span>
                    <h2>Let's pick up<br>where you left off.</h2>
                    <p>Log in to your EventFlow account.</p>
                </div>

                @if (session('status'))
                    <div class="login-status" role="status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="login-error" role="alert">
                        <strong>We couldn't sign you in.</strong>
                        @foreach ($errors->all() as $error)<span>{{ $error }}</span>@endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="login-form" data-loading-form>
                    @csrf
                    <div class="field">
                        <label for="email">Email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="you@school.edu" autocomplete="username" required autofocus>
                        @error('email')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <div class="field-label-row">
                            <label for="password">Password</label>
                            <a href="{{ route('password.request') }}">Forgot password?</a>
                        </div>
                        <div class="password-input">
                            <input id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required>
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password">Show</button>
                        </div>
                        @error('password')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <label class="checkbox-row">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                        <span class="checkbox-visual" aria-hidden="true"></span>
                        <span>Keep me signed in</span>
                    </label>
                    <button class="button button-primary button-wide login-submit" type="submit">
                        <span class="button-label">Sign in to EventFlow <span aria-hidden="true">→</span></span>
                        <span class="button-loading" aria-hidden="true">Signing you in…</span>
                    </button>
                </form>
                <div class="login-note"><span class="note-lock" aria-hidden="true">▣</span> Your school workspace is private and secure.</div>
                <div class="login-footer">New to EventFlow? <a href="{{ route('register') }}">Create an account</a></div>
            </div>
        </section>
    </main>
</body>
</html>
