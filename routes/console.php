<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| The contact sync runs every hour. With a batch size of 20 contacts per
| user per run, a user with 200 Google-connected contacts will have all
| of them checked approximately every 10 hours — a reasonable rolling
| cycle for a pastoral care tool.
|
| To enable the scheduler on your server, add this cron entry:
|   * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
|
| On Plesk, this can be configured under Scheduled Tasks in the control panel.
|
*/

Schedule::command('pastoreyes:sync-contacts')
    ->hourly()
    ->withoutOverlapping()   // prevent a slow run from queuing a second instance
    ->runInBackground()      // don't block other scheduled tasks
    ->appendOutputTo(storage_path('logs/contact-sync.log'));