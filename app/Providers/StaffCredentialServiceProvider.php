<?php

namespace App\Providers;

use App\Repositories\Contracts\StaffCredentialRepositoryInterface;
use App\Repositories\StaffCredential\StaffCredentialRepository;
use App\Services\Contracts\StaffCredentialServiceInterface;
use App\Services\StaffCredential\StaffCredentialService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class StaffCredentialServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind repository interface to implementation
        $this->app->bind(
            StaffCredentialRepositoryInterface::class,
            StaffCredentialRepository::class
        );

        // Bind service interface to implementation
        $this->app->bind(
            StaffCredentialServiceInterface::class,
            StaffCredentialService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register policy
        Gate::policy(\App\Models\StaffCredential::class, \App\Policies\StaffCredentialPolicy::class);
    }
}