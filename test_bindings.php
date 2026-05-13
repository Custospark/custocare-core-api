<?php

require './vendor/autoload.php';

use Illuminate\Foundation\Application;
use App\Services\Interfaces\ReferralServiceInterface;
use App\Services\ReferralService;
use App\Repositories\Interfaces\ReferralRepositoryInterface;
use App\Repositories\ReferralRepository;

$app = new Application();

// Bind repository interface to implementation
$app->bind(
    ReferralRepositoryInterface::class,
    ReferralRepository::class
);

// Bind service interface to implementation
$app->bind(
    ReferralServiceInterface::class,
    ReferralService::class
);

// Resolve the service
$service = $app->make(ReferralServiceInterface::class);

echo get_class($service) . " resolved successfully\n";

