<?php
// Add to routes/api.php

use App\Http\Controllers\Api\AllergyController;
use Illuminate\Support\Facades\Route;

// Allergy routes (nested under patient)
Route::prefix('patients/{patient}')->middleware(['auth:sanctum'])->group(function () {
    Route::get('allergies', [AllergyController::class, 'index']);
    Route::get('allergies/active', [AllergyController::class, 'active']);
    Route::post('allergies', [AllergyController::class, 'store']);
    Route::get('allergies/{allergy}', [AllergyController::class, 'show']);
    Route::put('allergies/{allergy}', [AllergyController::class, 'update']);
    Route::delete('allergies/{allergy}', [AllergyController::class, 'destroy']);
    Route::post('allergies/{allergy}/resolve', [AllergyController::class, 'resolve']);
    Route::post('allergies/{allergy}/restore', [AllergyController::class, 'restore']);
});

// Visit-scoped allergy route
Route::get('/allergies/visit/{visitId}', [AllergyController::class, 'visitAllergies'])->middleware(['auth:sanctum']);