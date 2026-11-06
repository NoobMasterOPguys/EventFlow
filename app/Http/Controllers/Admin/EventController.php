<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EventController extends Controller
{
    public function create(): View
    {
        return view('admin.events.create', [
            'categories' => EventCategory::query()->where('is_active', true)->orderBy('name')->pluck('name', 'name'),
            'minimumScheduleAt' => now()->addMinute()->startOfMinute()->format('Y-m-d\TH:i'),
        ]);
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'cancelled', 'completed'])],
            'creator' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'trashed' => ['nullable', Rule::in(['0', '1'])],
        ]);
        $query = Event::query()->with('creator:id,name,email')->withCount([
            'members',
            'tasks',
            'tasks as completed_tasks_count' => fn (Builder $query): Builder => $query->where('status', 'completed'),
        ]);

        if (($filters['trashed'] ?? '0') === '1') {
            $query->onlyTrashed();
        }

        $events = $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search): Builder => $query->where('title', 'like', '%'.$search.'%'))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category): Builder => $query->where('category', $category))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['creator'] ?? null, fn (Builder $query, int $creator): Builder => $query->where('created_by', $creator))
            ->when($filters['start_date'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('start_at', '>=', $date))
            ->when($filters['end_date'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('start_at', '<=', $date))
            ->latest('events.created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.events.index', [
            'events' => $events,
            'filters' => $filters,
            'categories' => EventCategory::query()->orderBy('name')->pluck('name', 'name'),
            'creators' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => [
                'nullable',
                'string',
                'max:100',
                Rule::exists('event_categories', 'name')->where('is_active', true),
            ],
            'location' => ['required', 'string', 'max:255'],
            'start_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'end_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:start_at'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);

        foreach (['start_at', 'end_at'] as $field) {
            if (CarbonImmutable::parse($validated[$field], config('app.timezone'))->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages([
                    $field => 'The event schedule must be later than the current time.',
                ]);
            }
        }

        $event = DB::transaction(function () use ($request, $validated): Event {
            $event = $request->user()->createdEvents()->create($validated);
            $event->members()->attach($request->user()->getKey(), [
                'role' => 'leader',
                'joined_at' => now(),
            ]);
            AuditLog::record($request->user(), 'event.admin_created', $event, [
                'title' => $event->title,
                'status' => $event->status,
            ], $request->ip());

            return $event;
        });

        return redirect()->route('admin.events.show', $event)->with('success', 'Event created.');
    }

    public function show(Event $event): View
    {
        $event->load(['creator:id,name,email', 'members:id,name,email'])
            ->loadCount([
                'members',
                'tasks',
                'tasks as completed_tasks_count' => fn (Builder $query): Builder => $query->where('status', 'completed'),
            ]);
        $categories = EventCategory::query()->orderBy('name')->pluck('name', 'name')->all();
        if ($event->category !== null) {
            $categories[$event->category] ??= $event->category;
        }

        return view('admin.events.show', [
            'event' => $event,
            'categories' => $categories,
            'tasks' => $event->tasks()->with(['creator:id,name', 'assignee:id,name'])->latest()->limit(50)->get(),
            'members' => $event->members()->orderBy('name')->get(),
            'overdueTaskCount' => $event->tasks()
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->where('status', '!=', 'completed')
                ->count(),
            'activity' => AuditLog::query()
                ->where('auditable_type', Event::class)
                ->where('auditable_id', $event->getKey())
                ->latest()
                ->limit(12)
                ->get(),
            'notificationHistory' => DB::table('notifications')
                ->join('users', 'users.id', '=', 'notifications.notifiable_id')
                ->where('notifications.notifiable_type', User::class)
                ->where('notifications.type', EventUpdate::class)
                ->where('notifications.data->event_id', $event->getKey())
                ->latest('notifications.created_at')
                ->limit(12)
                ->get([
                    'users.name as recipient_name',
                    'notifications.read_at',
                    'notifications.created_at',
                ]),
            'eligibleMembers' => User::query()
                ->where('account_status', 'active')
                ->whereDoesntHave('events', fn (Builder $query): Builder => $query->whereKey($event->getKey()))
                ->orderBy('name')
                ->limit(100)
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:100', Rule::exists('event_categories', 'name')],
            'location' => ['required', 'string', 'max:255'],
            'start_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'end_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:start_at'],
            'status' => ['required', Rule::in(['draft', 'published', 'cancelled', 'completed'])],
        ]);
        if ($validated['status'] === 'cancelled' && $event->status !== 'cancelled') {
            abort_unless($request->user()->can('events.cancel'), 403);
        }

        $scheduleErrors = [];
        foreach (['start_at', 'end_at'] as $field) {
            $newSchedule = CarbonImmutable::parse($validated[$field], config('app.timezone'));

            if ($event->{$field}->format('Y-m-d\TH:i') !== $validated[$field] && $newSchedule->lessThanOrEqualTo(now())) {
                $scheduleErrors[$field] = 'Your event schedule must be later than the current time. Please select a future date and time.';
            }
        }

        if ($scheduleErrors !== []) {
            throw ValidationException::withMessages($scheduleErrors);
        }

        $event->fill($validated)->save();
        if ($event->wasChanged()) {
            $this->notifyMembers($event, $request->user(), 'updated');
        }

        AuditLog::record($request->user(), 'event.admin_updated', $event, [
            'title' => $event->title,
            'status' => $event->status,
        ], $request->ip());

        return redirect()->route('admin.events.show', $event)->with('success', 'Event updated.');
    }

    public function updateStatus(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'published', 'cancelled', 'completed'])],
        ]);
        if ($validated['status'] === 'cancelled') {
            abort_unless($request->user()->can('events.cancel'), 403);
        }
        $previousStatus = $event->status;
        $event->update($validated);

        AuditLog::record($request->user(), 'event.status_changed', $event, [
            'from' => $previousStatus,
            'to' => $event->status,
        ], $request->ip());
        if ($event->wasChanged('status')) {
            $this->notifyMembers($event, $request->user(), 'status_changed');
        }

        return back()->with('success', 'Event status updated.');
    }

    public function addMember(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('account_status', 'active'),
                Rule::unique('event_members', 'user_id')->where('event_id', $event->getKey()),
            ],
        ]);
        $event->members()->attach($validated['user_id'], ['role' => 'member', 'joined_at' => now()]);
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
        $membership = $event->members()->whereKey($member->getKey())->firstOrFail();

        if ($membership->pivot->role === 'leader' && $event->leaders()->count() <= 1) {
            return back()->with('error', 'An event must retain at least one leader.');
        }

        DB::transaction(function () use ($event, $member, $request): void {
            $event->tasks()->withTrashed()->where('assigned_to', $member->getKey())->update(['assigned_to' => null]);
            $event->members()->detach($member->getKey());
            AuditLog::record($request->user(), 'event.member_removed', $event, [
                'user_id' => $member->getKey(),
            ], $request->ip());
        });

        return back()->with('success', 'Member removed and assigned tasks unassigned.');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $event->delete();

        AuditLog::record($request->user(), 'event.archived', $event, [
            'title' => $event->title,
        ], $request->ip());

        return redirect()->route('admin.events.index')->with('success', 'Event archived.');
    }

    public function restore(Request $request, Event $event): RedirectResponse
    {
        abort_unless($event->trashed(), 404);
        $event->restore();

        AuditLog::record($request->user(), 'event.restored', $event, [
            'title' => $event->title,
        ], $request->ip());

        return back()->with('success', 'Event restored.');
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
