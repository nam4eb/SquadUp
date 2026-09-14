<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('activities:send-reminders')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('operations:prune --force')->dailyAt('03:30')->withoutOverlapping()->onOneServer();
