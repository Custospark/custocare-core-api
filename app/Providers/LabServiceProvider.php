<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class LabServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // Register Template Contracts
        $this->app->bind(
            \App\Repositories\Lab\Contracts\LabTemplateRepositoryInterface::class,
            \App\Repositories\Lab\LabTemplateRepository::class
        );
        $this->app->bind(
            \App\Services\Lab\Contracts\LabTemplateServiceInterface::class,
            \App\Services\Lab\LabTemplateService::class
        );
        
        // Register Test Contracts
        $this->app->bind(
            \App\Repositories\Lab\Contracts\LabTestRepositoryInterface::class,
            \App\Repositories\Lab\LabTestRepository::class
        );
        $this->app->bind(
            \App\Services\Lab\Contracts\LabTestServiceInterface::class,
            \App\Services\Lab\LabTestService::class
        );
        
        // Register Template Field Contracts
        $this->app->bind(
            \App\Repositories\Lab\Contracts\LabTemplateFieldRepositoryInterface::class,
            \App\Repositories\Lab\LabTemplateFieldRepository::class
        );
        $this->app->bind(
            \App\Services\Lab\Contracts\LabTemplateFieldServiceInterface::class,
            \App\Services\Lab\LabTemplateFieldService::class
        );
        
        // Register Request Contracts
        $this->app->bind(
            \App\Repositories\Lab\Contracts\LabRequestRepositoryInterface::class,
            \App\Repositories\Lab\LabRequestRepository::class
        );
        $this->app->bind(
            \App\Services\Lab\Contracts\LabRequestServiceInterface::class,
            \App\Services\Lab\LabRequestService::class
        );
        
        // Register Request Item Contracts
        $this->app->bind(
            \App\Repositories\Lab\Contracts\LabRequestItemRepositoryInterface::class,
            \App\Repositories\Lab\LabRequestItemRepository::class
        );
        $this->app->bind(
            \App\Services\Lab\Contracts\LabRequestItemServiceInterface::class,
            \App\Services\Lab\LabRequestItemService::class
        );
        
        // Register Result Contracts
        $this->app->bind(
            \App\Repositories\Lab\Contracts\LabResultRepositoryInterface::class,
            \App\Repositories\Lab\LabResultRepository::class
        );
        $this->app->bind(
            \App\Services\Lab\Contracts\LabResultServiceInterface::class,
            \App\Services\Lab\LabResultService::class
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Load migrations
        // $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations/lab');
        
        // // Load routes
        // $this->loadRoutesFrom(__DIR__ . '/../../routes/api/lab.php');
        
        // // Register policies
        // // $this->registerPolicies();
        
        // // Publish assets
        // $this->publishes([
        //     __DIR__ . '/../../config/lab.php' => config_path('lab.php'),
        // ], 'lab-config');
    }
    
    /**
     * Register policies.
     *
     * @return void
     */
    protected function registerPolicies(): void
    {
        // Gate::policy(LabTemplate::class, LabTemplatePolicy::class);
        // Gate::policy(LabTest::class, LabTestPolicy::class);
        // Gate::policy(LabRequest::class, LabRequestPolicy::class);
        // Gate::policy(LabResult::class, LabResultPolicy::class);
    }
}