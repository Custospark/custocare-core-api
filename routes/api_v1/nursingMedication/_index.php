<?php

use App\Http\Controllers\Api\NursingMedicationAdministrationController;
use App\Http\Controllers\Api\NursingMedicationDoseController;
use App\Http\Controllers\Api\NursingTreatmentLogController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/nursing/medication-doses/missed', [NursingMedicationDoseController::class, 'missed']);
    Route::get('/nursing/medication-doses', [NursingMedicationDoseController::class, 'index']);
    Route::post('/nursing/medication-doses', [NursingMedicationDoseController::class, 'store']);
    Route::patch('/nursing/medication-doses/{dose}', [NursingMedicationDoseController::class, 'update']);

    Route::get('/nursing/medication-administrations', [NursingMedicationAdministrationController::class, 'index']);
    Route::post('/nursing/medication-administrations', [NursingMedicationAdministrationController::class, 'store']);

    Route::get('/nursing/treatment-logs', [NursingTreatmentLogController::class, 'index']);
    Route::post('/nursing/treatment-logs', [NursingTreatmentLogController::class, 'store']);
});
