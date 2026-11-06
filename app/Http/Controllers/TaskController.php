<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Task;
use App\Notifications\TaskUpdate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'filter' => ['nullable', Rule::in(['not_started', 'in_progress', 'completed', 'overdue'])],
            'status' => ['nullable', Rule::in(['not_started', 'in_progress', 'completed'])],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high'])],
        ]);

        $tasks = $request->user()->assignedTasks()
            ->with('event')
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhereHas('event', fn (Builder $eventQuery): Builder => $eventQuery->where('title', 'like', '%'.$search.'%'));
                });
            })
            ->when($validated['filter'] ?? null, function (Builder $query, string $filter): void {
                if ($filter === 'overdue') {
                    $query->whereNotNull('due_at')
                        ->where('due_at', '<', now())
                        ->where('status', '!=', 'completed');

                    return;
                }

                $query->where('status', $filter);
            })
            ->when($validated['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($validated['priority'] ?? null, fn (Builder $query, string $priority): Builder => $query->where('priority', $priority))
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderBy('id');

        return view('tasks.index', [
            'tasks' => $tasks->paginate(15)->withQueryString(),
            'filters' => $validated,
            'statuses' => ['not_started' => 'Not started', 'in_progress' => 'In progress', 'completed' => 'Completed'],
        ]);
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $event = $this->accessibleEvent($request, $event);
        Gate::authorize('update', $event);
        $validated = $request->validate($this->taskRules($event));
        $this->validateFutureDeadline($validated['due_at'] ?? null);
        $task = $event->tasks()->make(collect($validated)->except('assigned_to')->all());
        $task->creator()->associate($request->user());
        $task->assignee()->associate($validated['assigned_to'] ?? null);
        $task->save();
        AuditLog::record($request->user(), 'task.created', $task, [
            'event_id' => $event->getKey(),
            'assigned_to' => $task->assigned_to,
        ], $request->ip());
        if ($task->assignee?->receivesNotificationFor('task_assignments')) {
            $task->assignee->notify(new TaskUpdate($task, 'assigned'));
        }

        return redirect()->route('events.show', $event)->with('success', 'Task added to the event.');
    }

    public function edit(Request $request, Task $task): View
    {
        $task = $this->accessibleTask($request, $task);
        Gate::authorize('update', $task);
        $task->load(['event', 'assignee']);

        $minimumDueAt = now()->addMinute()->startOfMinute();

        return view('tasks.edit', [
            'task' => $task,
            'members' => $task->event->members()->orderBy('name')->get(),
            'isLeader' => Gate::allows('update', $task->event),
            'statuses' => ['not_started' => 'Not started', 'in_progress' => 'In progress', 'completed' => 'Completed'],
            'minimumDueAt' => $task->due_at === null || $task->due_at->greaterThanOrEqualTo($minimumDueAt)
                ? $minimumDueAt->format('Y-m-d\TH:i')
                : null,
        ]);
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $task = $this->accessibleTask($request, $task);
        Gate::authorize('update', $task);

        $previousAssignee = $task->assigned_to;
        $previousStatus = $task->status;

        if (Gate::allows('update', $task->event)) {
            $validated = $request->validate($this->taskRules($task->event));
            if (array_key_exists('due_at', $validated)) {
                if ($this->deadlineMatchesExisting($validated['due_at'], $task)) {
                    unset($validated['due_at']);
                } else {
                    $this->validateFutureDeadline($validated['due_at']);
                }
            }
            $task->fill(collect($validated)->except('assigned_to')->all());
            $task->assignee()->associate($validated['assigned_to'] ?? null);
        } else {
            $validated = $request->validate([
                'status' => ['required', Rule::in(['not_started', 'in_progress', 'completed'])],
            ]);
            $task->status = $validated['status'];
        }

        $task->save();
        AuditLog::record($request->user(), 'task.updated', $task, [
            'assigned_to' => ['from' => $previousAssignee, 'to' => $task->assigned_to],
            'status' => ['from' => $previousStatus, 'to' => $task->status],
        ], $request->ip());
        if (
            $task->wasChanged('assigned_to')
            && $task->assignee !== null
            && $task->assignee->receivesNotificationFor('task_assignments')
        ) {
            $task->assignee->notify(new TaskUpdate($task, 'reassigned'));
        }
        if (
            $task->wasChanged('status')
            && $task->assignee !== null
            && $task->assignee->receivesNotificationFor('task_status_updates')
        ) {
            $task->assignee->notify(new TaskUpdate($task, 'status_changed'));
        }

        return redirect()->route('events.show', $task->event)->with('success', 'Task updated.');
    }

    public function updateStatus(Request $request, Task $task): RedirectResponse
    {
        $task = $this->accessibleTask($request, $task);
        Gate::authorize('update', $task);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['not_started', 'in_progress', 'completed'])],
        ]);
        $previousStatus = $task->status;
        $task->update($validated);
        if ($task->wasChanged('status')) {
            AuditLog::record($request->user(), 'task.status_changed', $task, [
                'from' => $previousStatus,
                'to' => $task->status,
            ], $request->ip());
            if ($task->assignee?->receivesNotificationFor('task_status_updates')) {
                $task->assignee->notify(new TaskUpdate($task, 'status_changed'));
            }
        }

        return back()->with('success', 'Task status updated.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $task = $this->accessibleTask($request, $task);
        Gate::authorize('delete', $task);
        $event = $task->event;
        $task->delete();
        AuditLog::record($request->user(), 'task.archived', $task, [
            'event_id' => $event->getKey(),
            'title' => $task->title,
        ], $request->ip());

        return redirect()->route('events.show', $event)->with('success', 'Task archived.');
    }

    /**
     * @return array<string, array<int, string|In>>
     */
    private function taskRules(Event $event): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('event_members', 'user_id')->where('event_id', $event->id),
            ],
            'due_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'status' => ['required', Rule::in(['not_started', 'in_progress', 'completed'])],
        ];
    }

    private function validateFutureDeadline(?string $dueAt): void
    {
        if ($dueAt === null) {
            return;
        }

        $deadline = CarbonImmutable::parse($dueAt, config('app.timezone'));

        if ($deadline->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages([
                'due_at' => 'Your deadline must be later than the current time. Please select a future date and time.',
            ]);
        }
    }

    private function deadlineMatchesExisting(?string $dueAt, Task $task): bool
    {
        return $dueAt !== null
            && $task->due_at !== null
            && $task->due_at->format('Y-m-d\TH:i') === CarbonImmutable::parse($dueAt, config('app.timezone'))->format('Y-m-d\TH:i');
    }

    private function accessibleEvent(Request $request, Event $event): Event
    {
        return $request->user()->events()->whereKey($event->getKey())->firstOrFail();
    }

    private function accessibleTask(Request $request, Task $task): Task
    {
        $request->user()->events()->whereKey($task->event_id)->firstOrFail();

        return $task;
    }
}
