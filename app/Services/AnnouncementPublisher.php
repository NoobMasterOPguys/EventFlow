<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\SystemAnnouncement;
use Illuminate\Support\Facades\DB;

class AnnouncementPublisher
{
    public function publish(Announcement $announcement, User $actor): bool
    {
        return DB::transaction(function () use ($announcement, $actor): bool {
            $lockedAnnouncement = Announcement::query()
                ->lockForUpdate()
                ->findOrFail($announcement->getKey());

            if ($lockedAnnouncement->status === 'published') {
                return false;
            }

            if (! in_array($lockedAnnouncement->status, ['draft', 'scheduled'], true)) {
                return false;
            }

            $recipientCount = 0;
            User::query()
                ->where('account_status', 'active')
                ->orderBy('id')
                ->chunkById(100, function ($recipients) use ($lockedAnnouncement, &$recipientCount): void {
                    foreach ($recipients as $recipient) {
                        $recipient->notify(new SystemAnnouncement($lockedAnnouncement));
                        $recipientCount++;
                    }
                });

            $lockedAnnouncement->forceFill([
                'status' => 'published',
                'scheduled_at' => null,
                'published_at' => now(),
                'recipients_count' => $recipientCount,
            ])->save();

            AuditLog::record($actor, 'announcement.published', $lockedAnnouncement, [
                'recipients_count' => $recipientCount,
            ]);

            return true;
        });
    }
}
