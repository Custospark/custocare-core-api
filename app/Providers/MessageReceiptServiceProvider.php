<?php

namespace App\Providers;

use App\Repositories\Contracts\MessageReceiptRepositoryInterface;
use App\Repositories\MessageReceipt\MessageReceiptRepository;
use App\Services\Contracts\MessageReceiptServiceInterface;
use App\Services\MessageReceipt\MessageReceiptService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class MessageReceiptServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // Bind Repository Interface to Concrete Implementation
        $this->app->bind(
            MessageReceiptRepositoryInterface::class,
            MessageReceiptRepository::class
        );

        // Bind Service Interface to Concrete Implementation
        $this->app->bind(
            MessageReceiptServiceInterface::class,
            MessageReceiptService::class
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
        Gate::policy(\App\Models\MessageReceipt::class, \App\Policies\MessageReceiptPolicy::class);
        
        // Publish configuration if needed
        $this->publishes([
            __DIR__ . '/../config/message-receipt.php' => config_path('message-receipt.php'),
        ], 'message-receipt-config');
        
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        
        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/message-receipts.php');
    }
}