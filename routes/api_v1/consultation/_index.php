<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ConsultationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Consultations API Routes
|--------------------------------------------------------------------------
|
| Routes for managing patient consultation requests and responses
|
*/

Route::middleware(['auth:sanctum'])->group(function () {
    
    Route::prefix('consultations')->group(function () {
        
        // Standard CRUD operations
        Route::get('/', [ConsultationController::class, 'index']);
        Route::post('/', [ConsultationController::class, 'store']);
        
        // Specialized listing routes
        Route::get('/pending', [ConsultationController::class, 'pending']);
        Route::get('/urgent', [ConsultationController::class, 'urgent']);
        Route::get('/overdue', [ConsultationController::class, 'overdue']);
        Route::get('/statistics', [ConsultationController::class, 'statistics']);
        
        // Patient-specific routes
        Route::get('/patient/{patientId}', [ConsultationController::class, 'patientConsultations']);
        
        // Visit-specific routes
        Route::get('/visit/{visitId}', [ConsultationController::class, 'visitConsultations']);
        
        // Specialty-specific route
        Route::get('/specialty/{specialty}', [ConsultationController::class, 'bySpecialty']);
        
        // Consultation workflow operations
        Route::post('/{id}/accept', [ConsultationController::class, 'accept']);
        Route::post('/{id}/decline', [ConsultationController::class, 'decline']);
        Route::post('/{id}/complete', [ConsultationController::class, 'complete']);
        Route::post('/{id}/cancel', [ConsultationController::class, 'cancel']);
        Route::post('/{id}/schedule', [ConsultationController::class, 'schedule']);
        
        // Consultation CRUD operations
        Route::get('/{id}', [ConsultationController::class, 'show']);
        Route::put('/{id}', [ConsultationController::class, 'update']);
        Route::delete('/{id}', [ConsultationController::class, 'destroy']);
    });
});