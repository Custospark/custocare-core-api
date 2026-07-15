<?php

namespace App\Providers;

use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Message\MessageRepository;
use App\Services\Contracts\MessageServiceInterface;
use App\Services\Message\MessageService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class MessageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Repository Interface to Implementation
        $this->app->bind(
            MessageRepositoryInterface::class,
            MessageRepository::class
        );

        // Bind Service Interface to Implementation
        $this->app->bind(
            MessageServiceInterface::class,
            MessageService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register policy
        Gate::policy(\App\Models\Message::class, \App\Policies\MessagePolicy::class);
    }
}