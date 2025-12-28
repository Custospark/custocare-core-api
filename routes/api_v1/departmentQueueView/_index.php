<?php

use App\Http\Controllers\Api\DepartmentQueueViewController;
use Illuminate\Support\Facades\Route;

// Department Queue Views Routes
Route::prefix('department-queue-views')->middleware('auth:sunctum')->name('api.department-queue-views.')->group(function () {
    // Basic CRUD operations
    Route::get('/', [DepartmentQueueViewController::class, 'index'])->name('index');
    Route::post('/', [DepartmentQueueViewController::class, 'store'])->name('store');
    Route::get('/{department_queue_view}', [DepartmentQueueViewController::class, 'show'])->name('show');
    Route::put('/{department_queue_view}', [DepartmentQueueViewController::class, 'update'])->name('update');
    Route::delete('/{department_queue_view}', [DepartmentQueueViewController::class, 'destroy'])->name('destroy');
    
    // Special operations
    Route::get('/department-type', [DepartmentQueueViewController::class, 'byDepartmentAndType'])->name('by-department-type');
    Route::get('/critical', [DepartmentQueueViewController::class, 'critical'])->name('critical');
    Route::get('/dashboard', [DepartmentQueueViewController::class, 'dashboard'])->name('dashboard');
    Route::post('/batch-update', [DepartmentQueueViewController::class, 'batchUpdate'])->name('batch-update');
    Route::get('/analyze/wait-times', [DepartmentQueueViewController::class, 'analyzeWaitTimes'])->name('analyze.wait-times');
    Route::get('/generate/predictions', [DepartmentQueueViewController::class, 'generatePredictions'])->name('generate.predictions');
});