<?php
// routes/api.php (or relevant route file)

use App\Constants\ActionTypes;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
|
| Public endpoints for registration, login, email verification,
| and password recovery. Protected routes require authentication.
|
*/

Route::prefix('auth')->group(function () {
    // Public routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    // Email verification
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
    
    // Password reset
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    
    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::get('/test-verification-flow/{userId}', function ($userId) {
    try {
        $service = app(\App\Services\User\AccountRecoveryService::class);
        $result = $service->sendEmailVerification($userId, 'email',ActionTypes::LOGIN_CONFIRMATION);
        
        return response()->json([
            'success' => true,
            'message' => 'Verification email process completed',
            'result' => $result,
            'logs' => 'Check storage/logs/laravel.log for details'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});