<?php

declare(strict_types=1);

use App\Http\Controllers\Api\HubSupportTicketController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('hub-support')->group(function () {
    Route::post('tickets', [HubSupportTicketController::class, 'store']);
    Route::post('ticket', [HubSupportTicketController::class, 'store']); // alias (for frontend fallback)

    Route::get('tickets/{ref}', [HubSupportTicketController::class, 'show'])->whereUuid('ref');
    Route::get('tickets/track/{ref}', [HubSupportTicketController::class, 'show'])->whereUuid('ref'); // alias (for frontend fallback)
});

