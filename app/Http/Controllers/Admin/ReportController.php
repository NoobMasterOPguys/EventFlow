<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
        [$start, $end] = $this->dateRange($filters);

        return view('admin.reports.index', [
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'report' => $this->reportData($start, $end),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
        [$start, $end] = $this->dateRange($filters);
        $report = $this->reportData($start, $end);

        return response()->streamDownload(function () use ($report, $start, $end): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['EventFlow report', 'Start date', $start->toDateString(), 'End date', $end->toDateString()], ',', '"', '');

            foreach ([
                'User status' => $report['user_status'],
                'Event categories' => $report['event_categories'],
                'Event status' => $report['event_status'],
                'Task status' => $report['task_status'],
                'Task priority' => $report['task_priority'],
                'Administrator actions' => $report['admin_actions'],
            ] as $section => $rows) {
                fputcsv($output, [], ',', '"', '');
                fputcsv($output, [$section], ',', '"', '');
                fputcsv($output, ['Name', 'Count'], ',', '"', '');

                foreach ($rows as $name => $count) {
                    fputcsv($output, [$this->safeCsvCell((string) $name), (int) $count], ',', '"', '');
                }
            }

            fclose($output);
        }, 'eventflow-report-'.$start->toDateString().'-'.$end->toDateString().'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array{start_date?: string, end_date?: string}  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function dateRange(array $filters): array
    {
        $start = CarbonImmutable::parse(
            $filters['start_date'] ?? now()->startOfMonth()->toDateString(),
            config('app.timezone'),
        )->startOfDay();
        $end = CarbonImmutable::parse(
            $filters['end_date'] ?? now()->toDateString(),
            config('app.timezone'),
        )->endOfDay();

        return [$start, $end];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportData(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $users = User::query()->whereBetween('created_at', [$start, $end]);
        $events = Event::query()->whereBetween('created_at', [$start, $end]);
        $tasks = Task::query()->whereBetween('created_at', [$start, $end]);
        $auditLogs = AuditLog::query()->whereBetween('created_at', [$start, $end]);
        $taskCount = (clone $tasks)->count();

        return [
            'registrations' => (clone $users)->count(),
            'active_users' => (clone $users)->where('account_status', 'active')->count(),
            'suspended_users' => (clone $users)->where('account_status', 'suspended')->count(),
            'event_count' => (clone $events)->count(),
            'task_count' => $taskCount,
            'overdue_tasks' => (clone $tasks)
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->where('status', '!=', 'completed')
                ->count(),
            'completion_rate' => $taskCount === 0
                ? 0
                : (int) round((clone $tasks)->where('status', 'completed')->count() / $taskCount * 100),
            'user_status' => (clone $users)
                ->selectRaw('account_status, COUNT(*) as aggregate')
                ->groupBy('account_status')
                ->pluck('aggregate', 'account_status')
                ->all(),
            'event_categories' => (clone $events)
                ->selectRaw('COALESCE(category, ?) as category_label, COUNT(*) as aggregate', ['Uncategorized'])
                ->groupBy('category_label')
                ->orderByDesc('aggregate')
                ->pluck('aggregate', 'category_label')
                ->all(),
            'event_status' => (clone $events)
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->all(),
            'task_status' => (clone $tasks)
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->all(),
            'task_priority' => (clone $tasks)
                ->selectRaw('priority, COUNT(*) as aggregate')
                ->groupBy('priority')
                ->pluck('aggregate', 'priority')
                ->all(),
            'admin_actions' => (clone $auditLogs)
                ->selectRaw('action, COUNT(*) as aggregate')
                ->groupBy('action')
                ->orderByDesc('aggregate')
                ->pluck('aggregate', 'action')
                ->all(),
            'monthly_registrations' => $this->monthlyCounts(User::query(), 'created_at', $start, $end),
            'monthly_events' => $this->monthlyCounts(Event::query(), 'created_at', $start, $end),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function monthlyCounts(Builder $query, string $column, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $format = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";

        return (clone $query)
            ->whereBetween($column, [$start, $end])
            ->selectRaw("{$format} as month, COUNT(*) as aggregate")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('aggregate', 'month')
            ->all();
    }

    private function safeCsvCell(string $value): string
    {
        return preg_match('/^[\s\x00-\x20]*[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
