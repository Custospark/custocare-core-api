<?php

namespace App\Providers;

use App\Repositories\Contracts\InventoryLedgerRepositoryInterface;
use App\Repositories\InventoryLedger\InventoryLedgerRepository;
use App\Services\Contracts\InventoryLedgerServiceInterface;
use App\Services\InventoryLedger\InventoryLedgerService;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider for InventoryLedger module.
 * Registers bindings for repository and service interfaces.
 */
class InventoryLedgerServiceProvider extends ServiceProvider
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
            InventoryLedgerRepositoryInterface::class,
            InventoryLedgerRepository::class
        );

        // Bind Service Interface to Implementation
        $this->app->bind(
            InventoryLedgerServiceInterface::class,
            InventoryLedgerService::class
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register policies
        $this->registerPolicies();
        
        // Register routes
        $this->registerRoutes();
    }

    /**
     * Register policies for the module.
     *
     * @return void
     */
    protected function registerPolicies(): void
    {
        // Policies are automatically discovered by Laravel
        // This is here for future manual registration if needed
    }

    /**
     * Register routes for the module.
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        // Routes are registered in routes/api.php
        // This is here for future module-specific route registration
    }
}