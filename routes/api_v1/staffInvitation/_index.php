<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StaffInvitationController;

// Staff Invitations API Routes
Route::prefix('staff-invitations')->middleware(['auth:sanctum'])->name('staff-invitations.')->group(function () {
    // Public routes (if any)
    Route::get('/by-uuid/{uuid}', [StaffInvitationController::class, 'showByUuid'])
        ->name('show.by-uuid')
        ->withoutMiddleware('auth:api');
    
    // Authenticated routes
    Route::middleware(['auth:api'])->group(function () {
        // Basic CRUD operations
        Route::get('/', [StaffInvitationController::class, 'index'])->name('index');
        Route::post('/', [StaffInvitationController::class, 'store'])->name('store');
        Route::get('/{staff_invitation}', [StaffInvitationController::class, 'show'])->name('show');
        Route::put('/{staff_invitation}', [StaffInvitationController::class, 'update'])->name('update');
        Route::delete('/{staff_invitation}', [StaffInvitationController::class, 'destroy'])->name('destroy');
        
        // Additional actions
        Route::post('/{staff_invitation}/accept', [StaffInvitationController::class, 'accept'])->name('accept');
        Route::post('/{staff_invitation}/decline', [StaffInvitationController::class, 'decline'])->name('decline');
        Route::post('/{staff_invitation}/resend', [StaffInvitationController::class, 'resend'])->name('resend');
        Route::post('/{staff_invitation}/cancel', [StaffInvitationController::class, 'cancel'])->name('cancel');
        
        // Staff-specific routes
        Route::get('/my/invitations', [StaffInvitationController::class, 'myInvitations'])->name('my.invitations');
        Route::get('/my/pending-invitations', [StaffInvitationController::class, 'myPendingInvitations'])->name('my.pending-invitations');
        
        // Filtered routes
        Route::get('/staff/{staff_id}', [StaffInvitationController::class, 'getByStaff'])->name('by.staff');
        Route::get('/facility/{facility_id}', [StaffInvitationController::class, 'getByFacility'])->name('by.facility');
        
        // Batch operations
        Route::post('/batch/resend', [StaffInvitationController::class, 'batchResend'])->name('batch.resend');
        Route::post('/batch/cancel', [StaffInvitationController::class, 'batchCancel'])->name('batch.cancel');
        Route::post('/batch/delete', [StaffInvitationController::class, 'batchDelete'])->name('batch.delete');
        Route::post('/process-expired', [StaffInvitationController::class, 'processExpired'])->name('process.expired');
    });
});