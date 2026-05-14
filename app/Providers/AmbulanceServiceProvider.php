<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Interfaces\AmbulanceRepositoryInterface;
use App\Repositories\AmbulanceRepository;
use App\Repositories\Interfaces\AmbulanceTripRepositoryInterface;
use App\Repositories\AmbulanceTripRepository;
use App\Repositories\Interfaces\AmbulanceTripLogRepositoryInterface;
use App\Repositories\AmbulanceTripLogRepository;
use App\Repositories\Interfaces\AmbulanceCrewMemberRepositoryInterface;
use App\Repositories\AmbulanceCrewMemberRepository;
use App\Services\Interfaces\AmbulanceServiceInterface;
use App\Services\AmbulanceService;
use App\Services\Interfaces\AmbulanceCrewMemberServiceInterface;
use App\Services\AmbulanceCrewMemberService;
use App\Services\Interfaces\AmbulanceTripServiceInterface;
use App\Services\AmbulanceTripService;
use App\Services\Interfaces\AmbulanceTripLogServiceInterface;
use App\Services\AmbulanceTripLogService;

class AmbulanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Ambulance
        $this->app->bind(AmbulanceRepositoryInterface::class, AmbulanceRepository::class);
        $this->app->bind(AmbulanceServiceInterface::class, AmbulanceService::class);

        // Trip
        $this->app->bind(AmbulanceTripRepositoryInterface::class, AmbulanceTripRepository::class);
        $this->app->bind(AmbulanceTripServiceInterface::class, AmbulanceTripService::class);

        // Trip Log
        $this->app->bind(AmbulanceTripLogRepositoryInterface::class, AmbulanceTripLogRepository::class);
        $this->app->bind(AmbulanceTripLogServiceInterface::class, AmbulanceTripLogService::class);

        // Crew Member
        $this->app->bind(AmbulanceCrewMemberRepositoryInterface::class, AmbulanceCrewMemberRepository::class);
        $this->app->bind(AmbulanceCrewMemberServiceInterface::class, AmbulanceCrewMemberService::class);
    }

    public function boot(): void {}
}
