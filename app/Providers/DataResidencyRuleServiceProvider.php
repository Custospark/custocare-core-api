<?php

namespace App\Providers;

use App\Http\Controllers\Api\DataResidencyRuleController;
use App\Models\DataResidencyRule;
use App\Policies\DataResidencyRulePolicy;
use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\DataResidencyRuleRepositoryInterface;
use App\Repositories\DataResidencyRule\DataResidencyRuleRepository;
use App\Services\Contracts\DataResidencyRuleServiceInterface;
use App\Services\DataResidencyRule\DataResidencyRuleService;
use Illuminate\Support\Facades\Gate;

class DataResidencyRuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // Bind repository interface to implementation
        $this->app->bind(
            DataResidencyRuleRepositoryInterface::class,
            DataResidencyRuleRepository::class
        );

        // Bind service interface to implementation
        $this->app->bind(
            DataResidencyRuleServiceInterface::class,
            DataResidencyRuleService::class
        );

        // Register policy
        $this->app->when(DataResidencyRuleController::class)
                  ->needs(DataResidencyRuleServiceInterface::class)
                  ->give(DataResidencyRuleService::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register routes
        // $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        
        // Register views if needed
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'dataResidencyRule');
        
        // Register migrations if needed
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        
        // Register translations if needed
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'dataResidencyRule');
        
        // Register policies
        $this->registerPolicies();
    }

    /**
     * Register the application's policies.
     *
     * @return void
     */
    protected function registerPolicies(): void
    {
        Gate::policy(DataResidencyRule::class, DataResidencyRulePolicy::class);
    }
}