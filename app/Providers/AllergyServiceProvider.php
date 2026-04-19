<?php

namespace App\Providers;

use App\Repositories\Allergy\AllergyRepository as AllergyAllergyRepository;
use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\AllergyRepositoryInterface;
use App\Services\Contracts\AllergyServiceInterface;
use App\Services\Allergy\AllergyService;

class AllergyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
{
    // Bind Allergy Repository
    $this->app->bind(
        AllergyRepositoryInterface::class,
        AllergyAllergyRepository::class
    );
    
    // Bind Allergy Service
    $this->app->bind(
        AllergyServiceInterface::class,
        AllergyService::class
    );
}

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
