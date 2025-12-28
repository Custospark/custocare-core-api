<?php

namespace App\Providers;

use App\Repositories\ConversationParticipant\ConversationParticipantRepository;
use App\Repositories\Contracts\ConversationParticipantRepositoryInterface;
use App\Services\ConversationParticipant\ConversationParticipantService;
use App\Services\Contracts\ConversationParticipantServiceInterface;
use Illuminate\Support\ServiceProvider;

class ConversationParticipantServiceProvider extends ServiceProvider
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
            ConversationParticipantRepositoryInterface::class,
            ConversationParticipantRepository::class
        );

        // Bind Service Interface to Implementation
        $this->app->bind(
            ConversationParticipantServiceInterface::class,
            ConversationParticipantService::class
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}