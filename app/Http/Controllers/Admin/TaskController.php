<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskUpdate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'integer', Rule::exists('events', 'id')],
            'assignee' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'creator' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'status' => ['nullable', Rule::in(['not_started', 'in_progress', 'completed'])],
            'deadline' => ['nullable', Rule::in(['overdue', 'today', 'upcoming', 'unassigned'])],
            'trashed' => ['nullable', Rule::in(['0', '1'])],
        ]);
        $query = Task::query()->with(['event:id,title', 'creator:id,name', 'assignee:id,name']);

        if (($filters['trashed'] ?? '0') === '1') {
            $query->onlyTrashed();
        }

        $tasks = $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search): Builder => $query->where('title', 'like', '%'.$search.'%'))
            ->when($filters['event'] ?? null, fn (Builder $query, int $eventId): Builder => $query->where('event_id', $eventId))
            ->when($filters['assignee'] ?? null, fn (Builder $query, int $assignee): Builder => $query->where('assigned_to', $assignee))
            ->when($filters['creator'] ?? null, fn (Builder $query, int $creator): Builder => $query->where('created_by', $creator))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority): Builder => $query->where('priority', $priority))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['deadline'] ?? null, function (Builder $query, string $deadline): void {
                match ($deadline) {
                    'overdue' => $query->whereNotNull('due_at')->where('due_at', '<', now())->where('status', '!=', 'completed'),
                    'today' => $query->whereDate('due_at', now()->toDateString()),
                    'upcoming' => $query->where('due_at', '>', now()),
                    'unassigned' => $query->whereNull('assigned_to'),
                };
            })
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.tasks.index', [
            'tasks' => $tasks,
            'filters' => $filters,
            'events' => Event::query()->orderBy('title')->get(['id', 'title']),
            'users' => User::query()->where('account_status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Task $task): View
    {
        return view('admin.tasks.edit', [
            'task' => $task->load(['event', 'creator', 'assignee']),
            'members' => $task->event->members()->orderBy('name')->get(),
            'activity' => AuditLog::query()
                ->where('auditable_type', Task::class)
                ->where('auditable_id', $task->getKey())
                ->latest()
                ->limit(12)
                ->get(),
        ]);
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('event_members', 'user_id')->where('event_id', $task->event_id),
            ],
            'due_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'status' => ['required', Rule::in(['not_started', 'in_progress', 'completed'])],
        ]);

        if (
            ! empty($validated['assigned_to'])
            && (int) $validated['assigned_to'] !== $task->assigned_to
            && ! User::query()->whereKey($validated['assigned_to'])->where('account_status', 'active')->exists()
        ) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Tasks can only be assigned to active event members.',
            ]);
        }

        if (
            array_key_exists('due_at', $validated)
            && $validated['due_at'] !== $task->due_at?->format('Y-m-d\TH:i')
        ) {
            $deadline = $validated['due_at'] === null
                ? null
                : CarbonImmutable::parse($validated['due_at'], config('app.timezone'));

            if ($deadline !== null && $deadline->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages([
                    'due_at' => 'Your deadline must be later than the current time. Please select a future date and time.',
                ]);
            }
        }

        $before = $task->only(['title', 'assigned_to', 'due_at', 'priority', 'status']);
        $previousAssignee = $task->assigned_to;
        $previousStatus = $task->status;
        $task->fill(collect($validated)->except('assigned_to')->all());
        if (array_key_exists('assigned_to', $validated)) {
            $task->assignee()->associate($validated['assigned_to'] ?? null);
        }
        $task->save();

        AuditLog::record($request->user(), 'task.admin_updated', $task, [
            'before' => $before,
            'after' => $task->only(['title', 'assigned_to', 'due_at', 'priority', 'status']),
        ], $request->ip());
        if (
            $task->assignee !== null
            && $previousAssignee !== $task->assigned_to
            && $task->assignee->receivesNotificationFor('task_assignments')
        ) {
            $task->assignee->notify(new TaskUpdate($task, 'reassigned'));
        }
        if (
            $task->assignee !== null
            && $previousStatus !== $task->status
            && $task->assignee->receivesNotificationFor('task_status_updates')
        ) {
            $task->assignee->notify(new TaskUpdate($task, 'status_changed'));
        }

        return redirect()->route('admin.tasks.edit', $task)->with('success', 'Task updated.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $task->delete();

        AuditLog::record($request->user(), 'task.archived', $task, [
            'title' => $task->title,
            'event_id' => $task->event_id,
        ], $request->ip());

        return back()->with('success', 'Task archived.');
    }

    public function restore(Request $request, Task $task): RedirectResponse
    {
        abort_unless($task->trashed(), 404);
        $task->restore();

        AuditLog::record($request->user(), 'task.restored', $task, [
            'title' => $task->title,
            'event_id' => $task->event_id,
        ], $request->ip());

        return back()->with('success', 'Task restored.');
    }
}
