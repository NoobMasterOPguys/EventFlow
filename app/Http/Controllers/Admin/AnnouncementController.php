<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Notifications\SystemAnnouncement;
use App\Services\AnnouncementPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        $notificationQuery = DB::table('notifications')
            ->where('type', SystemAnnouncement::class);

        return view('admin.notifications.index', [
            'announcements' => Announcement::query()->with('creator')->latest()->paginate(15),
            'deliveryStats' => [
                'sent' => (clone $notificationQuery)->count(),
                'read' => (clone $notificationQuery)->whereNotNull('read_at')->count(),
                'unread' => (clone $notificationQuery)->whereNull('read_at')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedAnnouncement($request);
        $scheduledAt = $this->scheduledAt($validated['scheduled_at'] ?? null);
        $announcement = DB::transaction(function () use ($request, $validated, $scheduledAt): Announcement {
            $announcement = Announcement::query()->create([
                'created_by' => $request->user()->getKey(),
                'title' => $validated['title'],
                'body' => $validated['body'],
                'status' => $scheduledAt === null ? 'draft' : 'scheduled',
                'scheduled_at' => $scheduledAt,
            ]);

            AuditLog::record($request->user(), 'announcement.created', $announcement, [
                'title' => $announcement->title,
                'status' => $announcement->status,
            ], $request->ip());

            return $announcement;
        });

        return back()->with('success', $announcement->status === 'scheduled'
            ? 'Announcement scheduled.'
            : 'Announcement saved as a draft.');
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $validated = $this->validatedAnnouncement($request);
        $scheduledAt = $this->scheduledAt($validated['scheduled_at'] ?? null);
        DB::transaction(function () use ($request, $announcement, $validated, $scheduledAt): void {
            $lockedAnnouncement = Announcement::query()->lockForUpdate()->findOrFail($announcement->getKey());
            abort_unless(in_array($lockedAnnouncement->status, ['draft', 'scheduled'], true), 409);
            $before = $lockedAnnouncement->only(['title', 'body', 'scheduled_at', 'status']);
            $lockedAnnouncement->update([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'status' => $scheduledAt === null ? 'draft' : 'scheduled',
                'scheduled_at' => $scheduledAt,
            ]);

            AuditLog::record($request->user(), 'announcement.updated', $lockedAnnouncement, [
                'before' => $before,
                'after' => $lockedAnnouncement->only(['title', 'body', 'scheduled_at', 'status']),
            ], $request->ip());
        });

        return back()->with('success', 'Announcement updated.');
    }

    public function publish(Request $request, Announcement $announcement, AnnouncementPublisher $publisher): RedirectResponse
    {
        if (! $publisher->publish($announcement, $request->user())) {
            return back()->with('error', 'This announcement has already been published or cancelled.');
        }

        return back()->with('success', 'Announcement published to active users.');
    }

    public function cancel(Request $request, Announcement $announcement): RedirectResponse
    {
        DB::transaction(function () use ($request, $announcement): void {
            $lockedAnnouncement = Announcement::query()->lockForUpdate()->findOrFail($announcement->getKey());
            abort_unless($lockedAnnouncement->status === 'scheduled', 409);
            $lockedAnnouncement->update([
                'status' => 'cancelled',
                'scheduled_at' => null,
            ]);

            AuditLog::record($request->user(), 'announcement.cancelled', $lockedAnnouncement, [
                'title' => $lockedAnnouncement->title,
            ], $request->ip());
        });

        return back()->with('success', 'Scheduled announcement cancelled.');
    }

    /**
     * @return array{title: string, body: string, scheduled_at?: string|null}
     */
    private function validatedAnnouncement(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:10000'],
            'scheduled_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ]);
    }

    private function scheduledAt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $scheduledAt = CarbonImmutable::parse($value, config('app.timezone'));

        if ($scheduledAt->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'Choose a future date and time or leave scheduling blank to save a draft.',
            ]);
        }

        return $scheduledAt->toDateTimeString();
    }
}
