<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduled jobs (SPEC 5.9, 9a). The server runs `php artisan schedule:run` every minute from cron.
| Times are UTC unless a job decides per site.
*/

// Each site's digest goes out at its own local hour; the command checks every hour.
Schedule::command('ims:send-digests')->hourly()->withoutOverlapping();

$nightly = [
    Schedule::command('ims:backup')->dailyAt('07:00')->withoutOverlapping(), // 01:00 Colorado, 03:00 Georgia
    Schedule::command('ims:verify-stock')->dailyAt('07:30'),
];

if ($ops = config('ims.ops_email')) {
    foreach ($nightly as $job) {
        $job->emailOutputOnFailure($ops);
    }
}
