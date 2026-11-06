@extends('layouts.admin')

@section('title', 'Reports and analytics')
@section('heading', 'Reports and analytics')

@section('content')
    <section class="panel admin-panel">
        <div class="admin-section-heading"><div><span class="eyebrow">REPORTING CENTER</span><h2>Platform activity</h2></div>
            <a class="button button-secondary button-small" href="{{ route('admin.reports.export', $filters) }}">Export CSV</a>
        </div>
        <form class="admin-filters" method="GET" action="{{ route('admin.reports.index') }}">
            <label>From <input type="date" name="start_date" value="{{ $filters['start_date'] }}" required></label>
            <label>Through <input type="date" name="end_date" value="{{ $filters['end_date'] }}" required></label>
            <button class="button button-primary button-small" type="submit">Apply range</button>
            <button class="button button-secondary button-small" type="button" onclick="window.print()">Print</button>
        </form>
        <p class="admin-muted">Records created between {{ $filters['start_date'] }} and {{ $filters['end_date'] }}. Account and task status reflect their current database state.</p>
    </section>

    <div class="admin-stats-grid">
        @foreach ([
            ['Registrations', $report['registrations'], $report['active_users'].' active · '.$report['suspended_users'].' suspended'],
            ['Events created', $report['event_count'], 'Across all event categories'],
            ['Tasks created', $report['task_count'], $report['completion_rate'].'% complete'],
            ['Overdue tasks', $report['overdue_tasks'], 'Created in the selected period'],
        ] as [$label, $value, $detail])
            <section class="panel admin-stat-card"><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong><small>{{ $detail }}</small></section>
        @endforeach
    </div>

    <div class="admin-dashboard-grid">
        @foreach ([
            ['Monthly registrations', $report['monthly_registrations']],
            ['Monthly event creation', $report['monthly_events']],
            ['Events by category', $report['event_categories']],
            ['Events by status', $report['event_status']],
            ['Tasks by status', $report['task_status']],
            ['Tasks by priority', $report['task_priority']],
            ['Administrative actions', $report['admin_actions']],
        ] as [$title, $rows])
            <section class="panel admin-panel">
                <div class="admin-section-heading"><div><span class="eyebrow">SELECTED PERIOD</span><h2>{{ $title }}</h2></div></div>
                @if (count($rows) > 0)
                    @php($maxCount = max(1, max($rows)))
                    <div class="admin-breakdown">
                        @foreach ($rows as $name => $count)
                            <div><span>{{ str($name)->replace('_', ' ')->title() }}</span><strong>{{ number_format($count) }}</strong></div>
                        @endforeach
                    </div>
                    <div class="admin-bars" aria-label="{{ $title }}">
                        @foreach ($rows as $name => $count)
                            <div class="admin-bar-item"><span class="admin-bar-value">{{ $count }}</span><div class="admin-bar-track"><span style="height: {{ max(4, (int) round($count / $maxCount * 100)) }}%"></span></div><small>{{ str($name)->replace('_', ' ')->limit(12) }}</small></div>
                        @endforeach
                    </div>
                @else
                    <p class="admin-empty">No records were found for this period.</p>
                @endif
            </section>
        @endforeach
    </div>
@endsection
