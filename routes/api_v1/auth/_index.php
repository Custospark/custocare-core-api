<?php
// routes/api.php (or relevant route file)

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