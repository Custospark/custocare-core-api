<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class VisitEventServiceProvider extends ServiceProvider
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
            \App\Repositories\Contracts\VisitEventRepositoryInterface::class,
            \App\Repositories\VisitEvent\VisitEventRepository::class
        );

        // Bind Service Interface to Implementation
        $this->app->bind(
            \App\Services\Contracts\VisitEventServiceInterface::class,
            \App\Services\VisitEvent\VisitEventService::class
        );

        // Register additional bindings if needed
        $this->app->when(\App\Repositories\VisitEvent\VisitEventRepository::class)
            ->needs(\App\Models\VisitEvent::class)
            ->give(function () {
                return new \App\Models\VisitEvent();
            });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register policy
        Gate::policy(\App\Models\VisitEvent::class, \App\Policies\VisitEventPolicy::class);

        // Publish configuration if needed
        $this->publishes([
            __DIR__ . '/../../config/visit_event.php' => config_path('visit_event.php'),
        ], 'visit-event-config');

        // Load routes
        // $this->loadRoutesFrom(__DIR__ . '/../../routes/visit_event.php');
    }
}