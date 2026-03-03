<?php

declare(strict_types=1);

namespace App\Console\Commands\Billing;

use App\Services\Billing\Contracts\SubscriptionServiceInterface;
use Illuminate\Console\Command;

/**
 * CheckSubscriptionStatuses
 *
 * Runs daily to automatically transition subscription statuses:
 *  1. active/trial past billing date → past_due (grace starts)
 *  2. past_due grace expired         → suspended (access blocked)
 *  3. trial ended without payment    → past_due
 *
 * Schedule in routes/console.php:
 *   Schedule::command('billing:check-subscriptions')->dailyAt('00:05');
 */
class CheckSubscriptionStatuses extends Command
{
    protected $signature   = 'billing:check-subscriptions';
    protected $description = 'Process subscription status transitions: grace periods and suspensions.';

    public function __construct(
        private readonly SubscriptionServiceInterface $subscriptionService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('[Billing] Running subscription status check...');

        $this->subscriptionService->handleGracePeriod();

        $this->info('[Billing] ✅ Subscription status check complete.');

        return Command::SUCCESS;
    }
}
