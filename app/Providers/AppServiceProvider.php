<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Sunctum\PersonalAccessToken as SunctumPersonalAccessToken;
use Laravel\Sanctum\Sanctum;



class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
 

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Sanctum::usePersonalAccessTokenModel(SunctumPersonalAccessToken::class);
}    }
