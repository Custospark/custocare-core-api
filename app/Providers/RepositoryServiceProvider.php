<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Prescription\ClinicalTemplateRepository;
use App\Repositories\Contracts\ClinicalTemplateRepositoryInterface;
use App\Repositories\Contracts\PrescriptionItemRepositoryInterface;
use App\Repositories\Contracts\PrescriptionRepositoryInterface;
use App\Repositories\Prescription\PrescriptionItemRepository;
use App\Repositories\Prescription\PrescriptionRepository;
use Illuminate\Support\ServiceProvider;


class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PrescriptionRepositoryInterface::class,
            PrescriptionRepository::class
        );
        
        $this->app->bind(
            PrescriptionItemRepositoryInterface::class,
            PrescriptionItemRepository::class
        );
        
        $this->app->bind(
            ClinicalTemplateRepositoryInterface::class,
            ClinicalTemplateRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}