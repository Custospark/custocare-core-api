<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VisitActorController;

/*
|--------------------------------------------------------------------------
| API Routes for Visit Actors
|--------------------------------------------------------------------------
|
| These routes handle all CRUD operations for visit actors (staff participation
| in healthcare visits), including ending participations and retrieving
| related data.
|
*/

Route::middleware(['api', 'auth:sanctum'])->group(function () {
    Route::prefix('visit-actors')->group(function () {
        // RESTful resource routes
        Route::get('/', [VisitActorController::class, 'index'])
            ->name('visit-actors.index')
            ->middleware('can:viewAny,App\Models\VisitActor');
        
        Route::post('/', [VisitActorController::class, 'store'])
            ->name('visit-actors.store')
            ->middleware('can:create,App\Models\VisitActor');
        
        Route::get('/{id}', [VisitActorController::class, 'show'])
            ->name('visit-actors.show')
            ->where('id', '[0-9]+')
            ->middleware('can:view,visit_actor');
        
        Route::put('/{id}', [VisitActorController::class, 'update'])
            ->name('visit-actors.update')
            ->where('id', '[0-9]+')
            ->middleware('can:update,visit_actor');
        
        Route::delete('/{id}', [VisitActorController::class, 'destroy'])
            ->name('visit-actors.destroy')
            ->where('id', '[0-9]+')
            ->middleware('can:delete,visit_actor');
        
        // Custom action: End participation
        Route::post('/{id}/end-participation', [VisitActorController::class, 'endParticipation'])
            ->name('visit-actors.end-participation')
            ->where('id', '[0-9]+')
            ->middleware('can:endParticipation,visit_actor');
        
        // Relationship routes
        Route::get('/visit/{visitId}', [VisitActorController::class, 'byVisit'])
            ->name('visit-actors.by-visit')
            ->where('visitId', '[0-9]+')
            ->middleware('can:viewAny,App\Models\VisitActor');
        
        Route::get('/staff/{staffId}/active', [VisitActorController::class, 'activeParticipations'])
            ->name('visit-actors.active-participations')
            ->where('staffId', '[0-9]+')
            ->middleware('can:viewAny,App\Models\VisitActor');
        
        // Billing-specific routes (additional authorization)
        Route::prefix('billing')->middleware('can:viewBilling,App\Models\VisitActor')->group(function () {
            Route::get('/billable', [VisitActorController::class, 'index'])
                ->name('visit-actors.billable')
                ->middleware('scope:billing.read');
            
            Route::get('/{id}/billing-details', [VisitActorController::class, 'show'])
                ->name('visit-actors.billing-details')
                ->where('id', '[0-9]+');
        });
    });
});