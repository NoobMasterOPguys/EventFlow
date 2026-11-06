<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $user->unreadNotifications()->latest()->limit(6)->get();

        return response()->json([
            'count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications->map(function ($notification): array {
                $data = $notification->data;

                if (! empty($data['task_id'])) {
                    $url = route('tasks.edit', ['task' => $data['task_id']]);
                } elseif (! empty($data['event_id'])) {
                    $url = route('events.show', ['event' => $data['event_id']]);
                } else {
                    $url = route('notifications.index');
                }

                return [
                    'id' => $notification->id,
                    'title' => $data['deadline_label'] ?? $data['title'] ?? 'Notification',
                    'message' => $data['task_title']
                        ?? $data['event_title']
                        ?? $data['body']
                        ?? '',
                    'created_at' => $notification->created_at->diffForHumans(),
                    'url' => $url,
                ];
            })->values(),
        ]);
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->paginate(20);
        $taskIds = $notifications->getCollection()
            ->pluck('data.task_id')
            ->filter()
            ->unique()
            ->values();
        $eventIds = $notifications->getCollection()
            ->pluck('data.event_id')
            ->filter()
            ->unique()
            ->values();

        $tasks = $taskIds->isEmpty()
            ? collect()
            : Task::query()
                ->whereKey($taskIds)
                ->where('assigned_to', $user->getKey())
                ->whereHas('event.members', fn (Builder $query): Builder => $query->whereKey($user->getKey()))
                ->with('event')
                ->get()
                ->keyBy(fn (Task $task): int => $task->getKey());
        $events = $eventIds->isEmpty()
            ? collect()
            : Event::query()
                ->whereKey($eventIds)
                ->whereHas('members', fn (Builder $query): Builder => $query->whereKey($user->getKey()))
                ->get()
                ->keyBy(fn (Event $event): int => $event->getKey());

        return view('notifications.index', [
            'notifications' => $notifications,
            'tasks' => $tasks,
            'events' => $events,
        ]);
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->delete();

        return back()->with('success', 'Notification deleted.');
    }
}
