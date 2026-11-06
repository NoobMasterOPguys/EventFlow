<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\SystemSetting;
use App\Services\AnnouncementPublisher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('announcements:publish-scheduled')]
#[Description('Publish scheduled system announcements')]
class PublishScheduledAnnouncements extends Command
{
    public function handle(AnnouncementPublisher $publisher): int
    {
        SystemSetting::apply();
        $published = 0;

        Announcement::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->with('creator')
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get()
            ->each(function (Announcement $announcement) use ($publisher, &$published): void {
                if ($announcement->creator !== null && $publisher->publish($announcement, $announcement->creator)) {
                    $published++;
                }
            });

        $this->info("Published {$published} scheduled announcement(s).");

        return self::SUCCESS;
    }
}
