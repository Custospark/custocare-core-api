 <?php

use App\Http\Controllers\Api\PatientController;
use Illuminate\Support\Facades\Route;
 
 // ----------------------
    // Patient routes
    // ----------------------
    Route::prefix('patients')->name('patients.')->middleware(['api', 'auth:sanctum'])->group(function () {

        // General patient operations
        Route::get('/', [PatientController::class, 'index'])->name('index');
        Route::post('/', [PatientController::class, 'store'])->name('store');
        Route::post('/create-by-staff', [PatientController::class, 'createPatientByStaff']);
        Route::get('/search/lean', [PatientController::class, 'patientSearch']);
        Route::post('onboarding/consume-token', [PatientController::class, 'consumeOnboardingToken']);

        Route::get('/search', [PatientController::class, 'search'])->name('search');
        Route::get('/statistics', [PatientController::class, 'statistics'])->name('statistics');
        Route::get('/blood-type/{bloodType}', [PatientController::class, 'byBloodType'])->name('by-blood-type');
        Route::get('/requiring-isolation', [PatientController::class, 'requiringIsolation'])->name('requiring-isolation');
        // Individual patient operations
        Route::prefix('{patient}')->group(function () {
            Route::get('/', [PatientController::class, 'show'])->name('show');
            Route::put('/', [PatientController::class, 'update'])->name('update');
            Route::patch('/', [PatientController::class, 'update']); // partial updates
            Route::delete('/', [PatientController::class, 'destroy'])->name('destroy');

            // Special patient operations
            Route::post('/restore', [PatientController::class, 'restore'])->name('restore');
            Route::post('/status', [PatientController::class, 'updateStatus'])->name('update-status');
            Route::get('/export', [PatientController::class, 'export'])->name('export');
        });
    });

    // ----------------------
    // Alternative resource route for patients
    // ----------------------
    Route::apiResource('patients', PatientController::class)->except(['update']);
    Route::post('patients/{patient}', [PatientController::class, 'update']); // For partial updates