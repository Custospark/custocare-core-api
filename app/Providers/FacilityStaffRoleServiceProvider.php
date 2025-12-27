<?php

namespace App\Providers;

use App\Repositories\Contracts\FacilityStaffRoleRepositoryInterface;
use App\Repositories\FacilityStaffRole\FacilityStaffRoleRepository;
use App\Services\Contracts\FacilityStaffRoleServiceInterface;
use App\Services\FacilityStaffRole\FacilityStaffRoleService;
use Illuminate\Support\ServiceProvider;

class FacilityStaffRoleServiceProvider extends ServiceProvider
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
            FacilityStaffRoleRepositoryInterface::class,
            FacilityStaffRoleRepository::class
        );

        // Bind Service Interface to Implementation
        $this->app->bind(
            FacilityStaffRoleServiceInterface::class,
            FacilityStaffRoleService::class
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register policy if needed
        // Gate::policy(FacilityStaffRole::class, FacilityStaffRolePolicy::class);
    }
}