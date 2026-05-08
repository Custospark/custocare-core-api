<?php
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PatientAnalyticsController;
use Illuminate\Support\Facades\Route;

Route::prefix('patients')
    ->name('patients.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        // Collection / custom routes FIRST
        Route::get('/', [PatientController::class, 'index'])->name('index');
        Route::post('/', [PatientController::class, 'store'])->name('store');

        Route::post('/create-by-admin', [PatientController::class, 'createPatientByAdmin'])
            ->name('create-by-admin');

        // Optional backward-compatible alias
        Route::post('/create-by-staff', [PatientController::class, 'createPatientByAdmin'])
            ->name('create-by-staff');

        Route::get('/search/lean', [PatientController::class, 'patientSearch'])->name('search.lean');
        Route::post('/onboarding/consume-token', [PatientController::class, 'consumeOnboardingToken'])
            ->name('onboarding.consume-token');

        Route::get('/search', [PatientController::class, 'search'])->name('search');
        Route::get('/statistics', [PatientController::class, 'statistics'])->name('statistics');
        Route::get('/blood-type/{bloodType}', [PatientController::class, 'byBloodType'])->name('by-blood-type');
        Route::get('/requiring-isolation', [PatientController::class, 'requiringIsolation'])->name('requiring-isolation');

        //Hospital;/clinic Patient Analytics
        Route::get('/facility-patient-analytics', [PatientAnalyticsController::class, 'overview']);

        
        // Item routes LAST (specific paths before the generic GET /)
        Route::prefix('{patient}')->group(function () {
            Route::get('medical-history', [PatientController::class, 'medicalHistory'])->name('medical-history');

            Route::get('/', [PatientController::class, 'show'])->name('show');
            Route::put('/', [PatientController::class, 'update'])->name('update');
            Route::patch('/', [PatientController::class, 'update'])->name('patch');
            Route::delete('/', [PatientController::class, 'destroy'])->name('destroy');

            Route::post('/restore', [PatientController::class, 'restore'])->name('restore');
            Route::post('/status', [PatientController::class, 'updateStatus'])->name('update-status');
            Route::get('/export', [PatientController::class, 'export'])->name('export');
        });
        
    });
