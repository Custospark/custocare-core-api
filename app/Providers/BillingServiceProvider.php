<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Billing\Contracts\PaymentRepositoryInterface;
use App\Repositories\Billing\Contracts\PlanRepositoryInterface;
use App\Repositories\Billing\Contracts\SubscriptionRepositoryInterface;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Billing\PlanRepository;
use App\Repositories\Billing\SubscriptionRepository;
use App\Services\Billing\Contracts\PaymentServiceInterface;
use App\Services\Billing\Contracts\SubscriptionServiceInterface;
use App\Services\Billing\PaymentService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── Repositories ──────────────────────────────────────────────────
        $this->app->bind(PlanRepositoryInterface::class, PlanRepository::class);
        $this->app->bind(SubscriptionRepositoryInterface::class, SubscriptionRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);

        // ── Services ──────────────────────────────────────────────────────
        $this->app->bind(SubscriptionServiceInterface::class, SubscriptionService::class);
        $this->app->bind(PaymentServiceInterface::class, PaymentService::class);
    }

    public function boot(): void {}
}
