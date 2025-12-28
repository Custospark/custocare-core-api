<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\InvoiceLineItemRepositoryInterface;
use App\Repositories\InvoiceLineItem\InvoiceLineItemRepository;
use App\Services\Contracts\InvoiceLineItemServiceInterface;
use App\Services\InvoiceLineItem\InvoiceLineItemService;

class InvoiceLineItemServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Repository Interface to Concrete Implementation
        $this->app->bind(
            InvoiceLineItemRepositoryInterface::class,
            InvoiceLineItemRepository::class
        );

        // Bind Service Interface to Concrete Implementation
        $this->app->bind(
            InvoiceLineItemServiceInterface::class,
            InvoiceLineItemService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register policy
        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\InvoiceLineItem::class,
            \App\Policies\InvoiceLineItemPolicy::class
        );
    }
}