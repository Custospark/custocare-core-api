<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\ClinicalNoteRepositoryInterface;
use App\Repositories\Eloquent\ClinicalNoteRepository;
use App\Services\Contracts\ClinicalNoteServiceInterface;
use App\Services\ClinicalNoteService;

class ClinicalNoteServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Repository
        $this->app->bind(
            ClinicalNoteRepositoryInterface::class,
            ClinicalNoteRepository::class
        );

        // Register Service
        $this->app->bind(
            ClinicalNoteServiceInterface::class,
            ClinicalNoteService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load migrations if needed
        // $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations/clinical_notes');
        
        // Load routes if needed
        // $this->loadRoutesFrom(__DIR__ . '/../../routes/api_v1/clinicalNote/_index.php');
        
        // Publish configurations if needed
        // $this->publishes([
        //     __DIR__ . '/../../config/clinical_notes.php' => config_path('clinical_notes.php'),
        // ], 'clinical-notes-config');
    }
}