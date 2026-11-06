<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-timezone="{{ config('app.timezone') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Administration') · {{ $applicationName }}</title>
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="app-body admin-body">
    <a class="skip-link" href="#admin-main">Skip to main content</a>
    <div class="admin-shell">
        <button class="sidebar-scrim admin-sidebar-scrim" type="button" aria-label="Close administration navigation" data-sidebar-close></button>
        <aside class="admin-sidebar" id="admin-sidebar">
            <a class="brand" href="{{ route('admin.dashboard') }}"><x-brand-mark /><span>eventflow<span class="brand-period">.</span></span></a>
            <div class="admin-brand-label">Platform administration</div>
            <nav class="sidebar-nav" aria-label="Administrator navigation">
                @if (auth()->user()->hasAdminPermission('dashboard.view'))<a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}"><span class="nav-icon" aria-hidden="true">⌂</span><span>Overview</span></a>@endif
                @if (auth()->user()->hasAdminPermission('users.view'))<a class="nav-link {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}" href="{{ route('admin.users.index') }}"><span class="nav-icon" aria-hidden="true">♙</span><span>Users</span></a>@endif
                @if (auth()->user()->hasAdminPermission('events.view'))<a class="nav-link {{ request()->routeIs('admin.events.*') ? 'is-active' : '' }}" href="{{ route('admin.events.index') }}"><span class="nav-icon" aria-hidden="true">▦</span><span>Events</span></a>@endif
                @if (auth()->user()->hasAdminPermission('tasks.view'))<a class="nav-link {{ request()->routeIs('admin.tasks.*') ? 'is-active' : '' }}" href="{{ route('admin.tasks.index') }}"><span class="nav-icon" aria-hidden="true">✓</span><span>Tasks</span></a>@endif
                @if (auth()->user()->hasAdminPermission('categories.manage'))<a class="nav-link {{ request()->routeIs('admin.categories.*') ? 'is-active' : '' }}" href="{{ route('admin.categories.index') }}"><span class="nav-icon" aria-hidden="true">◈</span><span>Categories</span></a>@endif
                @if (auth()->user()->hasAdminPermission('notifications.manage'))<a class="nav-link {{ request()->routeIs('admin.notifications.*') ? 'is-active' : '' }}" href="{{ route('admin.notifications.index') }}"><span class="nav-icon" aria-hidden="true">♢</span><span>Notifications</span></a>@endif
                @if (auth()->user()->hasAdminPermission('reports.view'))<a class="nav-link {{ request()->routeIs('admin.reports.*') ? 'is-active' : '' }}" href="{{ route('admin.reports.index') }}"><span class="nav-icon" aria-hidden="true">▤</span><span>Reports</span></a>@endif
                @if (auth()->user()->hasAdminPermission('audit_logs.view'))<a class="nav-link {{ request()->routeIs('admin.audit.*') ? 'is-active' : '' }}" href="{{ route('admin.audit.index') }}"><span class="nav-icon" aria-hidden="true">⌕</span><span>Audit trail</span></a>@endif
                @if (auth()->user()->hasAdminPermission('settings.manage'))<a class="nav-link {{ request()->routeIs('admin.settings.*') ? 'is-active' : '' }}" href="{{ route('admin.settings.index') }}"><span class="nav-icon" aria-hidden="true">⚙</span><span>Settings</span></a>@endif
            </nav>
            <div class="admin-sidebar-bottom">
                <a class="nav-link" href="{{ route('dashboard') }}">← Return to workspace</a>
                <a class="admin-operator" href="{{ route('admin.profile') }}"><span class="avatar">{{ \Illuminate\Support\Str::of(auth()->user()->name)->substr(0, 1)->upper() }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->isSuperAdministrator() ? 'Super administrator' : 'Administrator profile' }}</small></div></a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="signout-button" type="submit"><span aria-hidden="true">↪</span> Sign out</button></form>
            </div>
        </aside>
        <main class="admin-main" id="admin-main">
            <header class="admin-topbar"><button class="mobile-menu-button admin-menu-button" type="button" aria-label="Open administration navigation" aria-expanded="false" aria-controls="admin-sidebar" data-sidebar-toggle><span aria-hidden="true">☰</span></button><div><span class="eyebrow">EVENTFLOW CONTROL CENTER</span><h1>@yield('heading', 'Administration')</h1></div><a class="button button-secondary button-small" href="{{ route('dashboard') }}">Open workspace</a></header>
            <div class="admin-content">
                @if (session('success'))<div class="flash-message flash-success" role="status"><span>✓</span>{{ session('success') }}</div>@endif
                @if (session('error'))<div class="flash-message flash-error" role="alert"><span>!</span>{{ session('error') }}</div>@endif
                @if ($errors->any())<div class="flash-message flash-error" role="alert"><span>!</span><div><strong>Please check the form and try again.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
