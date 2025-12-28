<?php

namespace App\Providers;

use App\Repositories\Contracts\InventoryItemRepositoryInterface;
use App\Repositories\InventoryItem\InventoryItemRepository;
use App\Services\Contracts\InventoryItemServiceInterface;
use App\Services\InventoryItem\InventoryItemService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class InventoryItemServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Repository Interface to Implementation
        $this->app->bind(
            InventoryItemRepositoryInterface::class,
            InventoryItemRepository::class
        );

        // Bind Service Interface to Implementation
        $this->app->bind(
            InventoryItemServiceInterface::class,
            InventoryItemService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register policies
        Gate::policy(\App\Models\InventoryItem::class, \App\Policies\InventoryItemPolicy::class);
    }
}