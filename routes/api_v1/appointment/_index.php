<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AppointmentController;

// Appointment Routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Standard RESTful routes
    Route::apiResource('appointments', AppointmentController::class);
    
    // Additional appointment actions
    Route::prefix('appointments/{appointment}')->group(function () {
        Route::post('/cancel', [AppointmentController::class, 'cancel'])
            ->name('appointments.cancel');
        
        Route::post('/confirm', [AppointmentController::class, 'confirm'])
            ->name('appointments.confirm');
        
        Route::post('/check-in', [AppointmentController::class, 'checkIn'])
            ->name('appointments.checkIn');
        
        Route::post('/complete', [AppointmentController::class, 'complete'])
            ->name('appointments.complete');
        
        Route::post('/reschedule', [AppointmentController::class, 'reschedule'])
            ->name('appointments.reschedule');
        
        Route::post('/send-reminder', [AppointmentController::class, 'sendReminder'])
            ->name('appointments.sendReminder');
    });
    
    // Appointment utilities
    Route::get('/appointments/availability/check', [AppointmentController::class, 'availability'])
        ->name('appointments.availability');
    
    Route::get('/appointments/statistics', [AppointmentController::class, 'statistics'])
        ->name('appointments.statistics');
});