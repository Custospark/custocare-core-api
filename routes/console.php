<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is responsible for defining Artisan closure-based commands
| and scheduled tasks for the application.
|
| Billing architecture:
| - Subscription lifecycle transitions are automated.
| - Grace period handling runs on a schedule.
| - Suspensions are enforced via middleware after status transition.
|
*/

/**
 * Default Laravel inspire command.
 */
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/*
|--------------------------------------------------------------------------
| Scheduled Commands
|--------------------------------------------------------------------------
|
| Production-grade billing scheduler.
| Runs subscription status transitions:
|   - active/trial → past_due
|   - past_due → suspended (after grace)
|   - trial ended → past_due
|
| Runs every 15 minutes for near real-time enforcement.
|
*/

Schedule::command('billing:check-subscriptions')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();