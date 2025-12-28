<?php

namespace App\Providers;

use App\Models\DepartmentQueueView;
use App\Policies\DepartmentQueueViewPolicy;
use App\Repositories\Contracts\DepartmentQueueViewRepositoryInterface;
use App\Repositories\DepartmentQueueView\DepartmentQueueViewRepository;
use App\Services\Contracts\DepartmentQueueViewServiceInterface;
use App\Services\DepartmentQueueView\DepartmentQueueViewService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class DepartmentQueueViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Repository Interface to Implementation
        $this->app->bind(
            DepartmentQueueViewRepositoryInterface::class,
            DepartmentQueueViewRepository::class
        );

        // Bind Service Interface to Implementation
        $this->app->bind(
            DepartmentQueueViewServiceInterface::class,
            DepartmentQueueViewService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register policy
        Gate::policy(DepartmentQueueView::class, DepartmentQueueViewPolicy::class);
    }
}