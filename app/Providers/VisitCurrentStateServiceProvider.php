<?php

namespace App\Providers;

use App\Models\VisitCurrentState;
use App\Policies\VisitCurrentStatePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class VisitCurrentStateServiceProvider extends ServiceProvider
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
            \App\Repositories\Contracts\VisitCurrentStateRepositoryInterface::class,
            \App\Repositories\VisitCurrentState\VisitCurrentStateRepository::class
        );
        
        // Bind Service interface to implementation
        $this->app->bind(
            \App\Services\Contracts\VisitCurrentStateServiceInterface::class,
            \App\Services\VisitCurrentState\VisitCurrentStateService::class
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register policies if needed
        Gate::policy(VisitCurrentState::class, VisitCurrentStatePolicy::class);
        
        // Publish configuration files if needed
        // $this->publishes([
        //     __DIR__.'/../config/visit_current_state.php' => config_path('visit_current_state.php'),
        // ], 'config');
    }
}