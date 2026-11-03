<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
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
        $task = $event->tasks()->make(collect($validated)->except('assigned_to')->all());
        $task->creator()->associate($request->user());
        $task->assignee()->associate($validated['assigned_to'] ?? null);
        $task->save();

        return redirect()->route('events.show', $event)->with('success', 'Task added to the event.');
    }

    public function edit(Request $request, Task $task): View
    {
        $task = $this->accessibleTask($request, $task);
        Gate::authorize('update', $task);
        $task->load(['event', 'assignee']);

        return view('tasks.edit', [
            'task' => $task,
            'members' => $task->event->members()->orderBy('name')->get(),
            'isLeader' => Gate::allows('update', $task->event),
            'statuses' => ['not_started' => 'Not started', 'in_progress' => 'In progress', 'completed' => 'Completed'],
        ]);
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $task = $this->accessibleTask($request, $task);
        Gate::authorize('update', $task);

        if (Gate::allows('update', $task->event)) {
            $validated = $request->validate($this->taskRules($task->event));
            $task->fill(collect($validated)->except('assigned_to')->all());
            $task->assignee()->associate($validated['assigned_to'] ?? null);
        } else {
            $validated = $request->validate([
                'status' => ['required', Rule::in(['not_started', 'in_progress', 'completed'])],
            ]);
            $task->status = $validated['status'];
        }

        $task->save();

        return redirect()->route('events.show', $task->event)->with('success', 'Task updated.');
    }

    public function updateStatus(Request $request, Task $task): RedirectResponse
    {
        $task = $this->accessibleTask($request, $task);
        Gate::authorize('update', $task);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['not_started', 'in_progress', 'completed'])],
        ]);
        $task->update($validated);

        return back()->with('success', 'Task status updated.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $task = $this->accessibleTask($request, $task);
        Gate::authorize('delete', $task);
        $event = $task->event;
        $task->delete();

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
            'due_at' => ['nullable', 'date'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'status' => ['required', Rule::in(['not_started', 'in_progress', 'completed'])],
        ];
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
