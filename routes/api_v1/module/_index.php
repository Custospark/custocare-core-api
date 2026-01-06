<?php
use App\Http\Controllers\Api\ModuleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('modules')->group(function () {
    Route::get('/', [ModuleController::class, 'index']);
    Route::post('/', [ModuleController::class, 'store']);
    Route::put('/{module}', [ModuleController::class, 'update']);
    Route::delete('/{module}', [ModuleController::class, 'destroy']);
    Route::post('/assign-default', [ModuleController::class, 'assignDefaultAccess']);
    Route::get('/role-defaults', [ModuleController::class, 'roleModuleDefaults']);
});
