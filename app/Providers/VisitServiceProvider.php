<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\VisitRepositoryInterface;
use App\Repositories\Visit\VisitRepository;
use App\Services\Contracts\VisitServiceInterface;
use App\Services\Visit\VisitService;
use Illuminate\Support\Facades\Gate;

class VisitServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // Bind Repository interface to implementation
        $this->app->bind(
            VisitRepositoryInterface::class,
            VisitRepository::class
        );

        // Bind Service interface to implementation
        $this->app->bind(
            VisitServiceInterface::class,
            VisitService::class
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
        Gate::policy(\App\Models\Visit::class, \App\Policies\VisitPolicy::class);
    }
}