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

// Retention sweep (read-only report, never deletes): Mondays 07:00.
Schedule::command('retention:review')
    ->weeklyOn(1, '07:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Audit review rota (read-only): 1st of month, 08:00.
Schedule::command('audit:review --days=30')
    ->monthlyOn(1, '08:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

/*
|--------------------------------------------------------------------------
| Queue worker (database driver) — same pattern as Custosell
|--------------------------------------------------------------------------
|
| Mails are queued (StandardEmail implements ShouldQueue). On shared hosting
| there is no supervisor, so the worker runs through the scheduler: every
| system cron tick fires schedule:run, which drains the queue and exits.
| --stop-when-empty + --max-time=50 guarantees the worker never overlaps
| the next minute tick. withoutOverlapping is belt-and-braces.
|
| Requires ONE system cron in hPanel (per environment), every minute:
|   /usr/bin/php /home/u214605677/domains/custocareai-api.custospark.com/artisan schedule:run
|
 */
Schedule::command('queue:work --stop-when-empty --max-time=50 --sleep=3 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();

// Keep the failed-jobs table from growing forever.
Schedule::command('queue:prune-failed --hours=72')
    ->daily();

// Billing health report: log errors, stuck payments, failed jobs, gateways.
// Read-only. Exit code nonzero when issue groups are found.
Schedule::command('billing:monitor --hours=24')
    ->dailyAt('06:00')
    ->withoutOverlapping();

// Expire abandoned pending gateway payments (verify-first: arrived money
// approves instead). Keeps failed/expired history; opens re-initiation.
Schedule::command('payments:expire-stale --minutes=1440')
    ->dailyAt('06:30')
    ->withoutOverlapping();