@extends('layouts.admin')

@section('title', 'Notification management')
@section('heading', 'Notification management')

@section('content')
    <div class="admin-stats-grid">
        @foreach ([['Delivered', $deliveryStats['sent']], ['Read', $deliveryStats['read']], ['Unread', $deliveryStats['unread']]] as [$label, $value])
            <section class="panel admin-stat-card"><span>Announcement {{ $label }}</span><strong>{{ number_format($value) }}</strong><small>In-app notification records</small></section>
        @endforeach
    </div>

    <section class="panel admin-panel">
        <div class="admin-section-heading"><div><span class="eyebrow">SYSTEM ANNOUNCEMENT</span><h2>Compose or schedule an update</h2></div></div>
        <p class="admin-muted">Announcements are delivered in-app to active accounts. Database delivery is transactional and duplicate-safe.</p>
        <form method="POST" action="{{ route('admin.notifications.store') }}" class="admin-form-grid">
            @csrf
            <div class="field admin-form-wide"><label for="title">Title</label><input id="title" name="title" maxlength="150" value="{{ old('title') }}" required></div>
            <div class="field admin-form-wide"><label for="body">Message</label><textarea id="body" name="body" rows="4" maxlength="10000" required>{{ old('body') }}</textarea></div>
            <div class="field"><label for="scheduled_at">Schedule for (optional)</label><input id="scheduled_at" name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}"><small>Leave blank to save as a draft.</small></div>
            <div class="form-actions"><button class="button button-primary" type="submit">Save announcement</button></div>
        </form>
    </section>

    <section class="panel admin-panel">
        <div class="admin-section-heading"><div><span class="eyebrow">HISTORY</span><h2>Announcements</h2></div></div>
        <div class="admin-announcement-list">
            @forelse ($announcements as $announcement)
                <article class="admin-announcement">
                    <div class="admin-section-heading">
                        <div><span class="status-badge status-{{ $announcement->status }}">{{ str($announcement->status)->title() }}</span><h3>{{ $announcement->title }}</h3><small>By {{ $announcement->creator->name }} · {{ $announcement->created_at->format('M j, Y · g:i A') }}</small></div>
                        @if ($announcement->status === 'published')<span class="admin-muted">{{ number_format($announcement->recipients_count) }} recipients · {{ $announcement->published_at?->diffForHumans() }}</span>@endif
                    </div>
                    <p>{{ $announcement->body }}</p>
                    @if (in_array($announcement->status, ['draft', 'scheduled'], true))
                        <form method="POST" action="{{ route('admin.notifications.update', $announcement) }}" class="admin-form-grid">
                            @csrf @method('PATCH')
                            <div class="field admin-form-wide"><label for="title-{{ $announcement->id }}">Title</label><input id="title-{{ $announcement->id }}" name="title" maxlength="150" value="{{ $announcement->title }}" required></div>
                            <div class="field admin-form-wide"><label for="body-{{ $announcement->id }}">Message</label><textarea id="body-{{ $announcement->id }}" name="body" rows="3" maxlength="10000" required>{{ $announcement->body }}</textarea></div>
                            <div class="field"><label for="scheduled-{{ $announcement->id }}">Schedule for</label><input id="scheduled-{{ $announcement->id }}" name="scheduled_at" type="datetime-local" value="{{ $announcement->scheduled_at?->format('Y-m-d\TH:i') }}"></div>
                            <div class="form-actions"><button class="button button-secondary button-small" type="submit">Save changes</button></div>
                        </form>
                        <div class="form-actions">
                            <form method="POST" action="{{ route('admin.notifications.publish', $announcement) }}" data-confirm="Publish this announcement to all active users?">@csrf @method('PATCH')<button class="button button-primary button-small" type="submit">Publish now</button></form>
                            @if ($announcement->status === 'scheduled')<form method="POST" action="{{ route('admin.notifications.cancel', $announcement) }}" data-confirm="Cancel this scheduled announcement?">@csrf @method('PATCH')<button class="button button-quiet button-small" type="submit">Cancel schedule</button></form>@endif
                        </div>
                    @endif
                </article>
            @empty
                <p class="admin-empty">No announcements have been created.</p>
            @endforelse
        </div>
        <div class="pagination-wrap">{{ $announcements->links() }}</div>
    </section>
@endsection
