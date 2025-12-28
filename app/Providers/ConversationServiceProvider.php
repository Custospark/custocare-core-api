<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ConversationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Repository Interface to Implementation
        $this->app->bind(
            \App\Repositories\Contracts\ConversationRepositoryInterface::class,
            \App\Repositories\Conversation\ConversationRepository::class
        );

        // Bind Service Interface to Implementation
        $this->app->bind(
            \App\Services\Contracts\ConversationServiceInterface::class,
            \App\Services\Conversation\ConversationService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register policies if needed
        \App\Models\Conversation::resolveRelationUsing('facility', function ($conversationModel) {
            return $conversationModel->belongsTo(\App\Models\Facility::class);
        });

        \App\Models\Conversation::resolveRelationUsing('visit', function ($conversationModel) {
            return $conversationModel->belongsTo(\App\Models\Visit::class);
        });

        \App\Models\Conversation::resolveRelationUsing('appointment', function ($conversationModel) {
            return $conversationModel->belongsTo(\App\Models\Appointment::class);
        });

        \App\Models\Conversation::resolveRelationUsing('createdBy', function ($conversationModel) {
            return $conversationModel->belongsTo(\App\Models\User::class, 'created_by_user_id');
        });
    }
}