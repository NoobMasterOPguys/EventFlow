<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $events = $user->events()
            ->where('start_at', '>=', now())
            ->where('status', '!=', 'cancelled')
            ->withCount([
                'tasks as active_tasks_count',
                'tasks as completed_tasks_count' => fn (Builder $query): Builder => $query->where('status', 'completed'),
            ])
            ->orderBy('start_at')
            ->limit(3)
            ->get();

        $assignedTasks = $user->assignedTasks()
            ->with('event')
            ->where('status', '!=', 'completed')
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->limit(6)
            ->get();

        $taskCounts = $user->assignedTasks()
            ->selectRaw(
                'COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status != ? AND due_at < ? THEN 1 ELSE 0 END) as overdue',
                ['completed', 'completed', now()],
            )
            ->first();

        return view('dashboard', [
            'events' => $events,
            'assignedTasks' => $assignedTasks,
            'eventCount' => $user->events()->count(),
            'upcomingEventCount' => $user->events()
                ->where('start_at', '>=', now())
                ->where('status', 'published')
                ->count(),
            'taskCount' => (int) $taskCounts->total,
            'completedTaskCount' => (int) $taskCounts->completed,
            'overdueTaskCount' => (int) $taskCounts->overdue,
        ]);
    }
}
