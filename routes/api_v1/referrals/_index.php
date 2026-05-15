<?php

use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\ReferralDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('referrals')
    ->name('referrals.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        // Collection / custom routes FIRST
        Route::get('/', [ReferralController::class, 'index'])->name('index');
        Route::post('/', [ReferralController::class, 'store'])->name('store');

        Route::get('/facility/{facilityId}/dashboard', [ReferralDashboardController::class, 'show'])
            ->name('dashboard');

        // Specific action routes
        Route::get('/pending', [ReferralController::class, 'pending'])->name('pending');
        Route::post('/{referral}/accept', [ReferralController::class, 'accept'])->name('accept');
        Route::post('/{referral}/reject', [ReferralController::class, 'reject'])->name('reject');
        Route::post('/{referral}/complete', [ReferralController::class, 'complete'])->name('complete');
        Route::post('/{referral}/cancel', [ReferralController::class, 'cancel'])->name('cancel');
        
        // Patient and facility specific routes
        Route::get('/patient/{patientId}', [ReferralController::class, 'patientReferrals'])->name('patient');
        Route::get('/facility/{facilityId}', [ReferralController::class, 'facilityReferrals'])->name('facility');
        Route::get('/from-facility/{facilityId}', [ReferralController::class, 'fromFacility'])->name('from-facility');
        Route::get('/to-facility/{facilityId}', [ReferralController::class, 'toFacility'])->name('to-facility');
        
        // Item routes LAST (specific paths before the generic GET /)
        Route::prefix('{referral}')->group(function () {
            Route::get('/', [ReferralController::class, 'show'])->name('show');
            Route::put('/', [ReferralController::class, 'update'])->name('update');
            Route::patch('/', [ReferralController::class, 'update'])->name('patch');
            Route::delete('/', [ReferralController::class, 'destroy'])->name('destroy');
        });
    });