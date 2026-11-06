<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tasks:send-deadline-notifications')->everyMinute()->withoutOverlapping(5);
Schedule::command('announcements:publish-scheduled')->everyMinute()->withoutOverlapping(5);
Schedule::command('events:send-schedule-notifications')->everyMinute()->withoutOverlapping(5);
