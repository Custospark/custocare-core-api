<?php

namespace App\Providers;

use App\Services\Billing\BillingService;
use App\Services\Contracts\BillingServiceInterface;
use Illuminate\Support\ServiceProvider;

/**
 * Billing Service Provider
 *
 * Registers billing service bindings
 */
class BillingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(BillingServiceInterface::class, BillingService::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
