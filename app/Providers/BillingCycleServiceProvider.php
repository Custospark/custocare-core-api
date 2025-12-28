<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\BillingCycleRepositoryInterface;
use App\Repositories\BillingCycle\BillingCycleRepository;
use App\Services\Contracts\BillingCycleServiceInterface;
use App\Services\BillingCycle\BillingCycleService;
use Illuminate\Support\Facades\Gate;

class BillingCycleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // Bind Repository Interface to Implementation
        $this->app->bind(
            BillingCycleRepositoryInterface::class,
            BillingCycleRepository::class
        );

        // Bind Service Interface to Implementation
        $this->app->bind(
            BillingCycleServiceInterface::class,
            BillingCycleService::class
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register policy
        Gate::policy(\App\Models\BillingCycle::class, \App\Policies\BillingCyclePolicy::class);
    }
}