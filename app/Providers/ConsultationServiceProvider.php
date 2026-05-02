<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\ConsultationRepositoryInterface;
use App\Repositories\Eloquent\ConsultationRepository;
use App\Services\Contracts\ConsultationServiceInterface;
use App\Services\ConsultationService;

class ConsultationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Repository
        $this->app->bind(
            ConsultationRepositoryInterface::class,
            ConsultationRepository::class
        );

        // Register Service
        $this->app->bind(
            ConsultationServiceInterface::class,
            ConsultationService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load migrations if needed
        // $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations/consultations');
        
        // Load routes if needed
        // $this->loadRoutesFrom(__DIR__ . '/../../routes/api_v1/consultation/_index.php');
        
        // Publish configurations if needed
        // $this->publishes([
        //     __DIR__ . '/../../config/consultations.php' => config_path('consultations.php'),
        // ], 'consultations-config');
    }
}