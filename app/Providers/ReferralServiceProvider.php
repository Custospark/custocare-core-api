<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Interfaces\ReferralRepositoryInterface;
use App\Repositories\ReferralRepository;
use App\Services\Interfaces\ReferralServiceInterface;
use App\Services\ReferralService;

class ReferralServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind repository interface to implementation
        $this->app->bind(
            ReferralRepositoryInterface::class,
            ReferralRepository::class
        );

        // Bind service interface to implementation
        $this->app->bind(
            ReferralServiceInterface::class,
            ReferralService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register policies if not using auto-discovery
        $this->registerPolicies();
    }

    /**
     * Register policies for the referral module
     */
    private function registerPolicies(): void
    {
        // Policies are typically auto-discovered, but we can register them here if needed
        // Gate::policy(Referral::class, ReferralPolicy::class);
    }
}