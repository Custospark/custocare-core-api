<?php

namespace App\Providers;

use App\Repositories\Contracts\MessageAttachmentRepositoryInterface;
use App\Repositories\MessageAttachment\MessageAttachmentRepository;
use App\Services\Contracts\MessageAttachmentServiceInterface;
use App\Services\MessageAttachment\MessageAttachmentService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class MessageAttachmentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Repository Interface to Implementation
        $this->app->bind(
            MessageAttachmentRepositoryInterface::class,
            MessageAttachmentRepository::class
        );

        // Bind Service Interface to Implementation
        $this->app->bind(
            MessageAttachmentServiceInterface::class,
            MessageAttachmentService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register policy
        Gate::policy(\App\Models\MessageAttachment::class, \App\Policies\MessageAttachmentPolicy::class);
    }
}