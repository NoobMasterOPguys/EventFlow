<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('eventflow-mark.svg') }}">
    <title>@yield('title') · {{ config('app.name', 'EventFlow') }}</title>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="legal-body">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <header class="legal-header">
        <a class="brand" href="{{ route('login') }}" aria-label="EventFlow sign in">
            <x-brand-mark />
            <span>eventflow<span class="brand-period">.</span></span>
        </a>
        <nav class="legal-header-nav" aria-label="Legal and account">
            <a href="{{ route('legal.privacy') }}">Privacy</a>
            <a href="{{ route('legal.terms') }}">Terms</a>
            <a class="button button-secondary button-small" href="{{ route('login') }}">Sign in</a>
        </nav>
    </header>
    <main class="legal-content" id="main-content">
        @if ($isDraft)
            <aside class="legal-draft-notice" role="status">
                <strong>Pre-launch draft.</strong>
                The operator, governing law, production service providers, and effective date are not all configured.
                Complete and review these details before making EventFlow publicly available or submitting it for OAuth verification.
            </aside>
        @endif
        @yield('content')
        <footer class="legal-footer">
            <span>Questions? <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a></span>
            <span><a href="{{ route('legal.privacy') }}">Privacy Policy</a> · <a href="{{ route('legal.terms') }}">Terms of Service</a></span>
        </footer>
    </main>
</body>
</html>
