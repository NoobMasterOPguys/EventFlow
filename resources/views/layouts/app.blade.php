<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-timezone="{{ config('app.timezone') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="google-site-verification" content="QbfSHptmTmYstIQ3e1HcdYuVWVlBnsN3zIZ0fciMVzI" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('eventflow-mark.svg') }}">
    <title>@yield('title', 'Workspace') · {{ $applicationName }}</title>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="app-body">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <div class="app-shell">
        <button class="sidebar-scrim" type="button" aria-label="Close navigation" data-sidebar-close></button>
        <aside class="sidebar" id="app-sidebar">
            <a class="brand" href="{{ route('dashboard') }}" aria-label="EventFlow home">
                <x-brand-mark />
                <span>eventflow<span class="brand-period">.</span></span>
            </a>

            <div class="sidebar-label">Workspace</div>
            <nav class="sidebar-nav" aria-label="Main navigation">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">
                    <span class="nav-icon" aria-hidden="true">⌂</span><span>Overview</span>
                </a>
                <a class="nav-link {{ request()->routeIs('events.*') ? 'is-active' : '' }}" href="{{ route('events.index') }}">
                    <span class="nav-icon" aria-hidden="true">▦</span><span>My events</span>
                </a>
                <a class="nav-link {{ request()->routeIs('calendar') ? 'is-active' : '' }}" href="{{ route('calendar') }}">
                    <span class="nav-icon" aria-hidden="true">▣</span><span>Calendar</span>
                </a>
                <a class="nav-link {{ request()->routeIs('tasks.*') ? 'is-active' : '' }}" href="{{ route('tasks.index') }}">
                    <span class="nav-icon" aria-hidden="true">✓</span><span>My tasks</span>
                </a>
                <a class="nav-link {{ request()->routeIs('notifications.*') ? 'is-active' : '' }}" href="{{ route('notifications.index') }}">
                    <span class="nav-icon" aria-hidden="true">♢</span><span>Notifications</span>
                    <span class="notification-count" data-notification-count @if ($unreadNotificationCount === 0) hidden @endif>{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
                </a>
                @if (auth()->user()->can('dashboard.view'))
                    <a class="nav-link" href="{{ route('admin.dashboard') }}">
                        <span class="nav-icon" aria-hidden="true">⌘</span><span>Administration</span>
                    </a>
                @endif
            </nav>

            <div class="sidebar-bottom">
                <div class="sidebar-tip">
                    <span class="tip-mark" aria-hidden="true">✦</span>
                    <p>Great things happen when everyone knows what comes next.</p>
                </div>
                <a class="profile-link {{ request()->routeIs('profile.*') ? 'is-active' : '' }}" href="{{ route('profile.edit') }}">
                    <span class="avatar">{{ \Illuminate\Support\Str::of(auth()->user()->name)->substr(0, 1)->upper() }}</span>
                    <span class="profile-name"><strong>{{ auth()->user()->name }}</strong><small>My profile</small></span>
                    <span class="profile-arrow" aria-hidden="true">↗</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="signout-button" type="submit"><span aria-hidden="true">↪</span> Sign out</button>
                </form>
            </div>
        </aside>

        <div class="main-shell">
            <header class="topbar">
                <button class="mobile-menu-button" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="app-sidebar" data-sidebar-toggle>
                    <span aria-hidden="true">☰</span>
                </button>
                <form class="topbar-search" action="{{ route('events.index') }}" method="GET" role="search">
                    <label class="sr-only" for="global-event-search">Search your events</label>
                    <span aria-hidden="true">⌕</span>
                    <input id="global-event-search" type="search" name="search" value="{{ request()->routeIs('events.*') ? request('search') : '' }}" placeholder="Find an event..." maxlength="100">
                    <kbd>/</kbd>
                </form>
                <div class="topbar-right">
                    <span class="today-label">{{ now()->format('D, M j') }}</span>
                    <div class="notification-popover-wrap">
                        <button class="topbar-notifications" type="button" aria-label="Notifications{{ $unreadNotificationCount > 0 ? ', '.$unreadNotificationCount.' unread' : '' }}" aria-expanded="false" aria-controls="notification-popover" data-notification-feed="{{ route('notifications.feed') }}" data-notification-toggle>
                            <span aria-hidden="true">♢</span>
                            @if ($unreadNotificationCount > 0)<span class="topbar-notification-count" data-notification-count>{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>@endif
                        </button>
                        <div class="notification-popover" id="notification-popover" role="dialog" aria-label="Unread notifications" data-notification-popover hidden>
                            <div class="notification-popover-heading"><strong>Notifications</strong><a href="{{ route('notifications.index') }}">View all</a></div>
                            <div class="notification-popover-loading" data-notification-loading>Loading notifications...</div>
                            <div class="notification-popover-list" data-notification-list></div>
                        </div>
                    </div>
                    <a class="topbar-avatar" href="{{ route('profile.edit') }}" aria-label="Open profile">
                        {{ \Illuminate\Support\Str::of(auth()->user()->name)->substr(0, 1)->upper() }}
                    </a>
                </div>
            </header>

            <main class="page-content" id="main-content">
                @if (session('success'))
                    <div class="flash-message flash-success" role="status">
                        <span aria-hidden="true">✓</span>{{ session('success') }}
                        <button type="button" aria-label="Dismiss message" data-dismiss>×</button>
                    </div>
                @endif
                @if (session('error'))
                    <div class="flash-message flash-error" role="alert">
                        <span aria-hidden="true">!</span>{{ session('error') }}
                        <button type="button" aria-label="Dismiss message" data-dismiss>×</button>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="flash-message flash-error" role="alert">
                        <span aria-hidden="true">!</span>
                        <div>
                            <strong>Please check the form and try again.</strong>
                            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                        <button type="button" aria-label="Dismiss message" data-dismiss>×</button>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
