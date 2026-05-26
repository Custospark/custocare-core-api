<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\UserMessageContactRepositoryInterface;
use App\Repositories\UserMessageContact\UserMessageContactRepository;
use App\Services\Contracts\UserMessageContactServiceInterface;
use App\Services\UserMessageContact\UserMessageContactService;
use Illuminate\Support\ServiceProvider;

class UserMessageContactServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            UserMessageContactRepositoryInterface::class,
            UserMessageContactRepository::class,
        );

        $this->app->bind(
            UserMessageContactServiceInterface::class,
            UserMessageContactService::class,
        );
    }
}
