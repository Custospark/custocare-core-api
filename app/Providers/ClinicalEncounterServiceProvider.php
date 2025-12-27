<?php

namespace App\Providers;

use App\Repositories\ClinicalEncounter\ClinicalEncounterRepository;
use App\Repositories\Contracts\ClinicalEncounterRepositoryInterface;
use App\Services\ClinicalEncounter\ClinicalEncounterService;
use App\Services\Contracts\ClinicalEncounterServiceInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ClinicalEncounterServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Repository Interface to Implementation
        $this->app->bind(
            ClinicalEncounterRepositoryInterface::class,
            ClinicalEncounterRepository::class
        );

        // Bind Service Interface to Implementation
        $this->app->bind(
            ClinicalEncounterServiceInterface::class,
            ClinicalEncounterService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register policy
        Gate::policy(\App\Models\ClinicalEncounter::class, \App\Policies\ClinicalEncounterPolicy::class);
    }
}