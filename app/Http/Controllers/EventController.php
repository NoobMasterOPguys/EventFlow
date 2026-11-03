<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\View\View;

class EventController extends Controller
{
    private const STATUSES = ['draft', 'published', 'cancelled', 'completed'];

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'sort' => ['nullable', Rule::in(['upcoming', 'newest', 'title'])],
        ]);

        $events = $request->user()->events()
            ->withCount([
                'tasks as active_tasks_count',
                'tasks as completed_tasks_count' => fn (Builder $query): Builder => $query->where('status', 'completed'),
            ])
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhere('location', 'like', '%'.$search.'%')
                        ->orWhere('category', 'like', '%'.$search.'%');
                });
            })
            ->when($validated['category'] ?? null, fn (Builder $query, string $category): Builder => $query->where('category', $category))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status));

        match ($validated['sort'] ?? 'upcoming') {
            'newest' => $events->orderByDesc('events.created_at')->orderByDesc('events.id'),
            'title' => $events->orderBy('events.title')->orderBy('events.id'),
            default => $events->orderByRaw('CASE WHEN events.start_at >= ? THEN 0 ELSE 1 END', [now()])
                ->orderBy('events.start_at')
                ->orderBy('events.id'),
        };

        return view('events.index', [
            'events' => $events->paginate(9)->withQueryString(),
            'categories' => $request->user()->events()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            'filters' => $validated,
        ]);
    }

    public function create(): View
    {
        return view('events.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->eventRules());

        $event = DB::transaction(function () use ($request, $validated): Event {
            $event = $request->user()->createdEvents()->create($validated);
            $event->members()->attach($request->user()->getKey(), [
                'role' => 'leader',
                'joined_at' => now(),
            ]);

            return $event;
        });

        return redirect()->route('events.show', $event)->with('success', 'Your event workspace is ready.');
    }

    public function show(Request $request, Event $event): View
    {
        $event = $this->accessibleEvent($request, $event);
        Gate::authorize('view', $event);

        $event->load('creator');
        $event->loadCount([
            'tasks as active_tasks_count',
            'tasks as completed_tasks_count' => fn (Builder $query): Builder => $query->where('status', 'completed'),
        ]);

        $event->setRelation('members', $event->members()
            ->withCount([
                'assignedTasks as event_tasks_count' => fn (Builder $query): Builder => $query->where('event_id', $event->id),
                'assignedTasks as completed_tasks_count' => fn (Builder $query): Builder => $query
                    ->where('event_id', $event->id)
                    ->where('status', 'completed'),
            ])
            ->orderBy('name')
            ->get());

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['not_started', 'in_progress', 'completed'])],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            'assignee' => [
                'nullable',
                'integer',
                Rule::exists('event_members', 'user_id')->where('event_id', $event->id),
            ],
            'unassigned' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $tasks = $event->tasks()
            ->with('assignee')
            ->when($validated['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($validated['priority'] ?? null, fn (Builder $query, string $priority): Builder => $query->where('priority', $priority))
            ->when($validated['assignee'] ?? null, fn (Builder $query, int $assignee): Builder => $query->where('assigned_to', $assignee))
            ->when($validated['unassigned'] ?? false, fn (Builder $query): Builder => $query->whereNull('assigned_to'))
            ->when($validated['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->limit(100)
            ->get();

        $availableUsers = User::query()
            ->whereDoesntHave('events', fn (Builder $query): Builder => $query->whereKey($event->id))
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'email']);

        return view('events.show', [
            'event' => $event,
            'tasks' => $tasks,
            'availableUsers' => $availableUsers,
            'filters' => $validated,
            'taskStatuses' => ['not_started' => 'Not started', 'in_progress' => 'In progress', 'completed' => 'Completed'],
            'isLeader' => Gate::allows('update', $event),
        ]);
    }

    public function edit(Request $request, Event $event): View
    {
        $event = $this->accessibleEvent($request, $event);
        Gate::authorize('update', $event);

        return view('events.edit', ['event' => $event]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $event = $this->accessibleEvent($request, $event);
        Gate::authorize('update', $event);
        $event->fill($request->validate($this->eventRules()))->save();

        return redirect()->route('events.show', $event)->with('success', 'Event details updated.');
    }

    public function updateStatus(Request $request, Event $event): RedirectResponse
    {
        $event = $this->accessibleEvent($request, $event);
        Gate::authorize('update', $event);
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);
        $event->update($validated);

        return redirect()->route('events.show', $event)->with('success', 'Event status updated.');
    }

    public function addMember(Request $request, Event $event): RedirectResponse
    {
        $event = $this->accessibleEvent($request, $event);
        Gate::authorize('update', $event);
        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('event_members', 'user_id')->where('event_id', $event->id),
            ],
        ], [
            'user_id.unique' => 'This person is already a member of the event.',
        ]);

        $event->members()->attach($validated['user_id'], [
            'role' => 'member',
            'joined_at' => now(),
        ]);

        return back()->with('success', 'Member added to the event.');
    }

    public function removeMember(Request $request, Event $event, User $member): RedirectResponse
    {
        $event = $this->accessibleEvent($request, $event);
        Gate::authorize('update', $event);
        $membership = $event->members()->whereKey($member->id)->firstOrFail();

        abort_unless($membership->pivot->role === 'member', 403);

        DB::transaction(function () use ($event, $member): void {
            $event->tasks()->withTrashed()->where('assigned_to', $member->id)->update(['assigned_to' => null]);
            $event->members()->detach($member->id);
        });

        return back()->with('success', 'Member removed. Their task history is preserved and assigned tasks are now unassigned.');
    }

    /**
     * @return array<string, array<int, string|In>>
     */
    private function eventRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'status' => ['required', Rule::in(self::STATUSES)],
        ];
    }

    private function accessibleEvent(Request $request, Event $event): Event
    {
        return $request->user()->events()->whereKey($event->getKey())->firstOrFail();
    }
}
