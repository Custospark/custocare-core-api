<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Contracts\WalkInCustomerServiceInterface;
use App\Services\WalkInCustomer\WalkInCustomerService;

class WalkInCustomerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(
            WalkInCustomerServiceInterface::class,
            WalkInCustomerService::class,
        );
    }
}
