<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\VitalRepositoryInterface;
use App\Repositories\Eloquent\VitalRepository;
use App\Services\Contracts\VitalServiceInterface;
use App\Services\VitalService;

class VitalServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Repository
        $this->app->bind(
            VitalRepositoryInterface::class,
            VitalRepository::class
        );

        // Register Service
        $this->app->bind(
            VitalServiceInterface::class,
            VitalService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load migrations if needed
        // $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations/vitals');
        
        // Load routes if needed
        // $this->loadRoutesFrom(__DIR__ . '/../../routes/api_v1/vital/_index.php');
        
        // Publish configurations if needed
        // $this->publishes([
        //     __DIR__ . '/../../config/vitals.php' => config_path('vitals.php'),
        // ], 'vitals-config');
    }
}