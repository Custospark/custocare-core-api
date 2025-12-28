<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\AuditLog\AuditLogRepository;
use App\Services\Contracts\AuditLogServiceInterface;
use App\Services\AuditLog\AuditLogService;

class AuditLogServiceProvider extends ServiceProvider
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
            AuditLogRepositoryInterface::class,
            AuditLogRepository::class
        );

        // Bind Service Interface to Implementation
        $this->app->bind(
            AuditLogServiceInterface::class,
            AuditLogService::class
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Publish configuration file (if needed)
        // $this->publishes([
        //     __DIR__.'/../config/audit_log.php' => config_path('audit_log.php'),
        // ], 'audit-log-config');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Load routes
        // $this->loadRoutesFrom(__DIR__.'/../../routes/audit_log.php');
    }
}