<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $eventStatuses = Event::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $taskStatuses = Task::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $taskPriorities = Task::query()->selectRaw('priority, COUNT(*) as aggregate')->groupBy('priority')->pluck('aggregate', 'priority');
        $overdueTasks = Task::query()
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->where('status', '!=', 'completed');
        $dueSoonTasks = Task::query()
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now(), now()->addDay()])
            ->where('status', '!=', 'completed');
        $registrationTrend = $this->dailyCounts(User::query(), 'created_at');
        $eventTrend = $this->dailyCounts(Event::query(), 'created_at');
        $taskCompletionTrend = $this->dailyCounts(Task::query()->where('status', 'completed'), 'updated_at');

        return view('admin.dashboard', [
            'stats' => [
                'users' => User::query()->count(),
                'active_users' => User::query()->where('account_status', 'active')->count(),
                'suspended_users' => User::query()->where('account_status', 'suspended')->count(),
                'administrators' => User::query()->where('system_role', 'admin')->count(),
                'events' => Event::query()->count(),
                'upcoming_events' => Event::query()->where('status', 'published')->where('start_at', '>=', now())->count(),
                'published_events' => $eventStatuses['published'] ?? 0,
                'draft_events' => $eventStatuses['draft'] ?? 0,
                'cancelled_events' => $eventStatuses['cancelled'] ?? 0,
                'completed_events' => $eventStatuses['completed'] ?? 0,
                'tasks' => Task::query()->count(),
                'pending_tasks' => $taskStatuses['not_started'] ?? 0,
                'in_progress_tasks' => $taskStatuses['in_progress'] ?? 0,
                'completed_tasks' => $taskStatuses['completed'] ?? 0,
                'overdue_tasks' => $overdueTasks->count(),
                'due_soon_tasks' => $dueSoonTasks->count(),
            ],
            'eventStatuses' => $eventStatuses,
            'taskStatuses' => $taskStatuses,
            'taskPriorities' => $taskPriorities,
            'registrationTrend' => $registrationTrend,
            'eventTrend' => $eventTrend,
            'taskCompletionTrend' => $taskCompletionTrend,
            'categoryMix' => EventCategory::query()->withCount('events')->orderByDesc('events_count')->limit(6)->get(),
            'recentUsers' => User::query()->latest()->limit(6)->get(['id', 'name', 'email', 'system_role', 'account_status', 'created_at']),
            'recentEvents' => Event::query()->with('creator:id,name')->latest()->limit(6)->get(),
            'recentTasks' => Task::query()->with(['event:id,title', 'assignee:id,name'])->latest('updated_at')->limit(6)->get(),
            'suspendedUsers' => User::query()->where('account_status', 'suspended')->latest('suspended_at')->limit(6)->get(['id', 'name', 'email', 'suspended_at']),
            'upcomingEvents' => Event::query()->with('creator:id,name')->where('status', 'published')->where('start_at', '>=', now())->orderBy('start_at')->limit(6)->get(),
            'overdueTaskList' => $overdueTasks->with(['event:id,title', 'assignee:id,name'])->orderBy('due_at')->limit(6)->get(),
            'recentActivity' => AuditLog::query()->with('actor:id,name')->latest()->limit(8)->get(),
        ]);
    }

    /**
     * @return array<int, array{date: string, count: int}>
     */
    private function dailyCounts(Builder $query, string $column): array
    {
        $start = CarbonImmutable::today()->subDays(6);
        $dateExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-%d', {$column})"
            : "DATE({$column})";
        $counts = (clone $query)
            ->whereBetween($column, [$start, $start->endOfDay()->addDays(6)])
            ->selectRaw("{$dateExpression} as aggregate_date, COUNT(*) as aggregate")
            ->groupBy('aggregate_date')
            ->pluck('aggregate', 'aggregate_date');

        return collect(range(0, 6))
            ->map(function (int $offset) use ($counts, $start): array {
                $date = $start->addDays($offset);

                return [
                    'date' => $date->format('M j'),
                    'count' => (int) ($counts[$date->toDateString()] ?? 0),
                ];
            })
            ->all();
    }
}
