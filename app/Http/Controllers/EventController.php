<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\User;
use App\Notifications\EventUpdate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EventController extends Controller
{
    private const STATUSES = ['draft', 'published', 'cancelled', 'completed'];

    public function index(Request $request): View
    {
        $categoryOptions = EventCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();
        $existingCategories = $request->user()->events()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        foreach ($existingCategories as $category) {
            $categoryOptions[$category] ??= $category;
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(array_keys($categoryOptions))],
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
            'categories' => $categoryOptions,
            'filters' => $validated,
        ]);
    }

    public function create(): View
    {
        return view('events.create', [
            'categories' => EventCategory::query()->where('is_active', true)->orderBy('name')->pluck('name', 'name')->all(),
            'minimumScheduleAt' => now()->addMinute()->startOfMinute()->format('Y-m-d\TH:i'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->eventRules());
        $this->validateFutureSchedule($validated);

        $event = DB::transaction(function () use ($request, $validated): Event {
            $event = $request->user()->createdEvents()->create($validated);
            $event->members()->attach($request->user()->getKey(), [
                'role' => 'leader',
                'joined_at' => now(),
            ]);
            AuditLog::record($request->user(), 'event.created', $event, [
                'title' => $event->title,
            ], $request->ip());

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
        $categories = EventCategory::query()->where('is_active', true)->orderBy('name')->pluck('name', 'name')->all();

        if ($event->category !== null && ! array_key_exists($event->category, $categories)) {
            $categories[$event->category] = $event->category;
        }
        $minimumScheduleAt = now()->addMinute()->startOfMinute();

        return view('events.edit', [
            'event' => $event,
            'categories' => $categories,
            'minimumScheduleAt' => $event->start_at->greaterThanOrEqualTo($minimumScheduleAt)
                && $event->end_at->greaterThanOrEqualTo($minimumScheduleAt)
                ? $minimumScheduleAt->format('Y-m-d\TH:i')
                : null,
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $event = $this->accessibleEvent($request, $event);
        Gate::authorize('update', $event);
        $validated = $request->validate($this->eventRules($event));

        $this->validateFutureSchedule($validated, $event);
        $this->removeUnchangedSchedule($validated, $event);

        $before = $event->only(['title', 'category', 'location', 'start_at', 'end_at', 'status']);
        $event->fill($validated)->save();
        AuditLog::record($request->user(), 'event.updated', $event, [
            'before' => $before,
            'after' => $event->only(['title', 'category', 'location', 'start_at', 'end_at', 'status']),
        ], $request->ip());
        if ($event->wasChanged()) {
            $this->notifyMembers($event, $request->user(), 'updated');
        }

        return redirect()->route('events.show', $event)->with('success', 'Event details updated.');
    }

    public function updateStatus(Request $request, Event $event): RedirectResponse
    {
        $event = $this->accessibleEvent($request, $event);
        Gate::authorize('update', $event);
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);
        $previousStatus = $event->status;
        $event->update($validated);
        AuditLog::record($request->user(), 'event.status_changed', $event, [
            'from' => $previousStatus,
            'to' => $event->status,
        ], $request->ip());
        if ($event->wasChanged('status')) {
            $this->notifyMembers($event, $request->user(), 'status_changed');
        }

        return redirect()->route('events.show', $event)->with('success', 'Event status updated.');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $event = $this->accessibleEvent($request, $event);
        Gate::authorize('update', $event);

        DB::transaction(function () use ($event, $request): void {
            $event = Event::query()->lockForUpdate()->findOrFail($event->getKey());
            $event->delete();
            AuditLog::record($request->user(), 'event.archived', $event, [
                'title' => $event->title,
            ], $request->ip());
        });

        return redirect()->route('events.index')->with('success', 'Event archived. Its history is preserved and an administrator can restore it.');
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
        $newMember = User::query()->findOrFail($validated['user_id']);
        if ($newMember->receivesNotificationFor('event_updates')) {
            $newMember->notify(new EventUpdate($event, 'member_added'));
        }
        AuditLog::record($request->user(), 'event.member_added', $event, [
            'user_id' => $validated['user_id'],
        ], $request->ip());

        return back()->with('success', 'Member added to the event.');
    }

    public function removeMember(Request $request, Event $event, User $member): RedirectResponse
    {
        $event = $this->accessibleEvent($request, $event);
        Gate::authorize('update', $event);
        $membership = $event->members()->whereKey($member->id)->firstOrFail();

        abort_unless($membership->pivot->role === 'member', 403);

        DB::transaction(function () use ($event, $member, $request): void {
            $event->tasks()->withTrashed()->where('assigned_to', $member->id)->update(['assigned_to' => null]);
            $event->members()->detach($member->id);
            AuditLog::record($request->user(), 'event.member_removed', $event, [
                'user_id' => $member->getKey(),
            ], $request->ip());
        });

        return back()->with('success', 'Member removed. Their task history is preserved and assigned tasks are now unassigned.');
    }

    /**
     * @return array<string, array<int, string|In>>
     */
    private function eventRules(?Event $event = null): array
    {
        $categoryOptions = EventCategory::query()->where('is_active', true)->pluck('name')->all();

        if ($event?->category !== null && ! in_array($event->category, $categoryOptions, true)) {
            $categoryOptions[] = $event->category;
        }

        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:100', Rule::in($categoryOptions)],
            'location' => ['required', 'string', 'max:255'],
            'start_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'end_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:start_at'],
            'status' => ['required', Rule::in(self::STATUSES)],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function validateFutureSchedule(array $validated, ?Event $event = null): void
    {
        $errors = [];

        foreach (['start_at', 'end_at'] as $field) {
            if (
                $event !== null
                && $event->{$field}->format('Y-m-d\TH:i') === $validated[$field]
            ) {
                continue;
            }

            if (CarbonImmutable::parse($validated[$field], config('app.timezone'))->lessThanOrEqualTo(now())) {
                $errors[$field] = 'Your event schedule must be later than the current time. Please select a future date and time.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function removeUnchangedSchedule(array &$validated, Event $event): void
    {
        foreach (['start_at', 'end_at'] as $field) {
            if ($event->{$field}->format('Y-m-d\TH:i') === $validated[$field]) {
                unset($validated[$field]);
            }
        }
    }

    private function accessibleEvent(Request $request, Event $event): Event
    {
        return $request->user()->events()->whereKey($event->getKey())->firstOrFail();
    }

    private function notifyMembers(Event $event, User $actor, string $kind): void
    {
        $event->members()
            ->where('users.id', '!=', $actor->getKey())
            ->get()
            ->each(function (User $member) use ($event, $kind): void {
                if ($member->receivesNotificationFor('event_updates')) {
                    $member->notify(new EventUpdate($event, $kind));
                }
            });
    }
}
