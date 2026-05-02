<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\DiagnosisRepositoryInterface;
use App\Repositories\Eloquent\DiagnosisRepository;
use App\Services\Contracts\DiagnosisServiceInterface;
use App\Services\DiagnosisService;

class DiagnosisServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Repository
        $this->app->bind(
            DiagnosisRepositoryInterface::class,
            DiagnosisRepository::class
        );

        // Register Service
        $this->app->bind(
            DiagnosisServiceInterface::class,
            DiagnosisService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load migrations if needed
        // $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations/diagnoses');
        
        // Load routes if needed
        // $this->loadRoutesFrom(__DIR__ . '/../../routes/api_v1/diagnosis/_index.php');
        
        // Publish configurations if needed
        // $this->publishes([
        //     __DIR__ . '/../../config/diagnoses.php' => config_path('diagnoses.php'),
        // ], 'diagnoses-config');
    }
}