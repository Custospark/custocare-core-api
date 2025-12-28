<?php

namespace App\Providers;

use App\Repositories\Contracts\PatientVisitSummaryViewRepositoryInterface;
use App\Repositories\PatientVisitSummaryView\PatientVisitSummaryViewRepository;
use App\Services\Contracts\PatientVisitSummaryViewServiceInterface;
use App\Services\PatientVisitSummaryView\PatientVisitSummaryViewService;
use Illuminate\Support\ServiceProvider;

class PatientVisitSummaryViewServiceProvider extends ServiceProvider
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
            PatientVisitSummaryViewRepositoryInterface::class,
            PatientVisitSummaryViewRepository::class
        );

        // Bind Service Interface to Implementation
        $this->app->bind(
            PatientVisitSummaryViewServiceInterface::class,
            PatientVisitSummaryViewService::class
        );

        // Register Policy
        $this->app->singleton(
            \App\Policies\PatientVisitSummaryViewPolicy::class,
            function ($app) {
                return new \App\Policies\PatientVisitSummaryViewPolicy();
            }
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register any events, observers, or other bootstrapping code here
        $this->registerObservers();
        $this->registerMacros();
    }

    /**
     * Register model observers.
     *
     * @return void
     */
    private function registerObservers(): void
    {
        // Example: Register an observer for the PatientVisitSummaryView model
        // \App\Models\PatientVisitSummaryView::observe(\App\Observers\PatientVisitSummaryViewObserver::class);
    }

    /**
     * Register custom macros.
     *
     * @return void
     */
    private function registerMacros(): void
    {
        // Example: Add custom collection macros if needed
        // \Illuminate\Support\Collection::macro('summarize', function () {
        //     // Custom logic here
        // });
    }
}