<?php
// app/Http/Controllers/Api/AuthController.php (updated version)

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Requests\Auth\ResendVerificationRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\User\UserService;
use App\Services\User\AccountRecoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param UserService $userService
     * @param AccountRecoveryService $accountRecoveryService
     */
    protected UserService $userService;
    protected AccountRecoveryService $accountRecoveryService;

    public function __construct(
        UserService $userService,
        AccountRecoveryService $accountRecoveryService
    ) {
        $this->userService = $userService;
        $this->accountRecoveryService = $accountRecoveryService;
    }

    /**
     * Register a new user.
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = $this->userService->register($request->validated());

            // Send email verification
            $this->accountRecoveryService->sendEmailVerification($user->id, 'email');

            return response()->json([
                'success' => true,
                'code' => 'REGISTRATION_SUCCESS',
                'message' => 'Account created successfully! Please verify your email.',
                'user' => new UserResource($user),
                'token' => null, // No token until email verified
                'requires_mfa' => true,
            ], 201);

        } catch (\Exception $e) {
            // Determine if the error is due to a duplicate email or national ID
            $duplicateEmail = str_contains($e->getMessage(), 'email already exists');
            $duplicateNationalId = str_contains($e->getMessage(), 'national ID already exists');

            $status = $duplicateEmail || $duplicateNationalId ? 409 : 500;

            if ($duplicateEmail) {
                $code = 'EMAIL_ALREADY_REGISTERED';
                $message = 'A user with this email already exists.';
            } elseif ($duplicateNationalId) {
                $code = 'NATIONAL_ID_ALREADY_REGISTERED';
                $message = 'A user with this national ID already exists.';
            } else {
                $code = 'REGISTRATION_FAILED';
                $message = 'Registration failed. Please try again later.';
            }

            return response()->json([
                'success' => false,
                'code' => $code,
                'message' => $message,
                'user' => null,
                'token' => null,
                'requires_mfa' => false,
            ], $status);
        }
    }

    /**
     * Login user.
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only(['email', 'password', 'mfa_code']);

        Log::info('Login attempt', [
            'email' => $credentials['email'],
            'has_mfa_code' => !empty($credentials['mfa_code'])
        ]);

        $result = $this->userService->login(
            $credentials,
            $request->ip(),
            $request->userAgent()
        );

        $responseData = [
            'success' => $result['success'],
            'code' => $result['code'],
            'message' => $result['message'],
            'requires_mfa' => $result['requires_mfa'],
            'user' => $result['user'] ? new UserResource($result['user']) : null,
            'token' => $result['token'],
        ];

        $statusCode = match($result['code']) {
            'LOGIN_SUCCESS' => 200,
            'MFA_REQUIRED' => 200,
            'EMAIL_NOT_VERIFIED' => 403,
            'ACCOUNT_LOCKED' => 423,
            'INVALID_CREDENTIALS', 'INVALID_MFA' => 401,
            default => 400,
        };

        return response()->json($responseData, $statusCode);
    }

    /**
     * Verify email with token or OTP.
     *
     * @param VerifyEmailRequest $request
     * @return JsonResponse
     */
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $result = $this->accountRecoveryService->verifyEmail(
                $validated['user_id'],
                $validated['code'],
                $validated['is_token'] ?? false
            );

            return response()->json([
                'success' => true,
                'code' => 'EMAIL_VERIFIED',
                'message' => 'Email verified successfully. You can now log in.',
                'user' => null,
                'token' => null,
                'requires_mfa' => false,
            ]);

        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            
            return response()->json([
                'success' => false,
                'code' => 'VERIFICATION_FAILED',
                'message' => $e->getMessage(),
                'user' => null,
                'token' => null,
                'requires_mfa' => false,
            ], $statusCode);
        }
    }

    /**
     * Resend email verification.
     *
     * @param ResendVerificationRequest $request
     * @return JsonResponse
     */
    public function resendVerification(ResendVerificationRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $result = $this->accountRecoveryService->sendEmailVerification(
                $validated['user_id'],
                $validated['channel'] ?? 'email'
            );

            return response()->json([
                'success' => true,
                'code' => 'VERIFICATION_SENT',
                'message' => $result['message'],
                'expires_at' => $result['expires_at'],
                'user' => null,
                'token' => null,
                'requires_mfa' => false,
            ]);

        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            
            return response()->json([
                'success' => false,
                'code' => 'RESEND_FAILED',
                'message' => $e->getMessage(),
                'user' => null,
                'token' => null,
                'requires_mfa' => false,
            ], $statusCode);
        }
    }

    /**
     * Forgot password - initiate reset.
     *
     * @param ForgotPasswordRequest $request
     * @return JsonResponse
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $result = $this->accountRecoveryService->initiatePasswordReset(
                $validated['email'],
                $validated['channel'] ?? 'email'
            );

            return response()->json([
                'success' => true,
                'code' => 'RESET_INITIATED',
                'message' => $result['message'],
                'expires_at' => $result['expires_at'] ?? null,
                'user' => null,
                'token' => null,
                'requires_mfa' => false,
            ]);

        } catch (\Exception $e) {
            // Always return success for security (don't reveal if email exists)
            return response()->json([
                'success' => true,
                'code' => 'RESET_INITIATED',
                'message' => 'If the email exists, a reset code has been sent',
                'user' => null,
                'token' => null,
                'requires_mfa' => false,
            ]);
        }
    }

    /**
     * Reset password with token/OTP.
     *
     * @param ResetPasswordRequest $request
     * @return JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $result = $this->accountRecoveryService->resetPassword(
                $validated['email'],
                $validated['code'],
                $validated['new_password'],
                $validated['is_token'] ?? false
            );

            return response()->json([
                'success' => true,
                'code' => 'PASSWORD_RESET',
                'message' => 'Password reset successfully. You can now log in with your new password.',
                'user' => null,
                'token' => null,
                'requires_mfa' => false,
            ]);

        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 400;
            
            return response()->json([
                'success' => false,
                'code' => 'RESET_FAILED',
                'message' => $e->getMessage(),
                'user' => null,
                'token' => null,
                'requires_mfa' => false,
            ], $statusCode);
        }
    }

    /**
     * Logout user.
     *
     * @param LogoutRequest $request
     * @return JsonResponse
     */
    public function logout(LogoutRequest $request): JsonResponse
    {
        $this->userService->logout($request->user());

        return response()->json([
            'success' => true,
            'code' => 'LOGOUT_SUCCESS',
            'message' => 'Successfully logged out',
            'requires_mfa' => false,
            'user' => null,
            'token' => null,
        ]);
    }

    /**
     * Get authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 'USER_RETRIEVED',
            'message' => 'User retrieved successfully',
            'requires_mfa' => false,
            'user' => new UserResource($request->user()),
            'token' => null,
        ]);
    }
}