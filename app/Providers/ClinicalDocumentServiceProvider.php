<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\ClinicalDocument\ClinicalDocumentRepository;
use App\Repositories\Contracts\ClinicalDocumentRepositoryInterface;
use App\Services\ClinicalDocument\ClinicalDocumentService;
use App\Services\Contracts\ClinicalDocumentServiceInterface;
use Illuminate\Support\Facades\Gate;

class ClinicalDocumentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Repository Interface to Implementation
        $this->app->bind(
            ClinicalDocumentRepositoryInterface::class,
            ClinicalDocumentRepository::class
        );

        // Bind Service Interface to Implementation
        $this->app->bind(
            ClinicalDocumentServiceInterface::class,
            ClinicalDocumentService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register policy
        Gate::policy(\App\Models\ClinicalDocument::class, \App\Policies\ClinicalDocumentPolicy::class);
        
        // Publish configuration if needed (optional)
        // $this->publishes([
        //     __DIR__.'/../config/clinical_documents.php' => config_path('clinical_documents.php'),
        // ], 'clinical-documents-config');
    }
}