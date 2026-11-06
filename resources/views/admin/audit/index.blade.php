@extends('layouts.admin')

@section('title', 'Audit trail')
@section('heading', 'Audit trail')

@section('content')
    <section class="panel admin-panel">
        <form class="admin-filters admin-filters-wrap" method="GET" action="{{ route('admin.audit.index') }}">
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search actor, action, or IP" aria-label="Search audit log">
            <select name="action" aria-label="Filter by action"><option value="">All actions</option>@foreach ($actions as $action)<option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ str($action)->replace('.', ' ')->title() }}</option>@endforeach</select>
            <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}" aria-label="From date">
            <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}" aria-label="Through date">
            <button class="button button-primary button-small" type="submit">Apply</button><a class="button button-secondary button-small" href="{{ route('admin.audit.index') }}">Reset</a>
        </form>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Target</th><th>Details</th><th>IP address</th></tr></thead><tbody>
            @forelse ($logs as $log)
                <tr><td>{{ $log->created_at->format('M j, Y · g:i:s A') }}</td><td>{{ $log->actor?->name ?? 'System' }}</td><td><span class="admin-role-badge">{{ str($log->action)->replace('.', ' ')->replace('_', ' ')->title() }}</span></td><td>{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</td><td><code class="audit-metadata">{{ json_encode($log->metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code></td><td>{{ $log->ip_address ?? '—' }}</td></tr>
            @empty
                <tr><td colspan="6" class="admin-empty">No audit entries match the selected filters.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="pagination-wrap">{{ $logs->links() }}</div>
    </section>
@endsection
