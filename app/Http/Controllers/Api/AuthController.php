<?php
// app/Http/Controllers/Api/AuthController.php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Constants\ActionTypes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Requests\Auth\ResendVerificationRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\User\AccountRecoveryService;
use App\Services\User\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handles all authentication-related HTTP endpoints.
 *
 * Dependencies:
 *   UserService            – registration, login, logout, profile
 *   AccountRecoveryService – email verification, password reset
 *
 * Neither service depends on the other, so there is NO circular dependency.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly UserService            $userService,
        private readonly AccountRecoveryService $accountRecoveryService
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Registration
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Register a new user and send an email-verification notification.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = $this->userService->register($request->validated());

            // Trigger email verification (fires EmailVerificationRequested event)
            $this->accountRecoveryService->sendEmailVerification($user->id, 'email',ActionTypes::ACCOUNT_CREATION);

            return response()->json([
                'success'      => true,
                'code'         => 'REGISTRATION_SUCCESS',
                'message'      => 'Account created successfully! Please verify your email.',
                'user'         => new UserResource($user),
                'token'        => null,  // No token until email is verified
                'requires_mfa' => false,
            ], 201);

        } catch (\Exception $e) {
            [$status, $code, $message] = $this->resolveRegistrationError($e);

            return response()->json([
                'success'      => false,
                'code'         => $code,
                'message'      => $message,
                'user'         => null,
                'token'        => null,
                'requires_mfa' => false,
            ], $status);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Login
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Authenticate a user.
     * Returns a token on success, or signals MFA / email-verification requirements.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only(['email', 'password', 'mfa_code']);

        Log::info('Login attempt', [
            'email'        => $credentials['email'],
            'has_mfa_code' => !empty($credentials['mfa_code']),
        ]);

        $result = $this->userService->login(
            $credentials,
            $request->ip(),
            $request->userAgent() ?? ''
        );

        $statusCode = match ($result['code']) {
            'LOGIN_SUCCESS'        => 200,
            'MFA_REQUIRED'         => 200,
            'EMAIL_NOT_VERIFIED'   => 403,
            'ACCOUNT_LOCKED'       => 423,
            'INVALID_CREDENTIALS',
            'INVALID_MFA'          => 401,
            default                => 400,
        };

        return response()->json([
            'success'      => $result['success'],
            'code'         => $result['code'],
            'message'      => $result['message'],
            'requires_mfa' => $result['requires_mfa'],
            'user'         => $result['user'] ? new UserResource($result['user']) : null,
            'token'        => $result['token'],
        ], $statusCode);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Email Verification
    // ─────────────────────────────────────────────────────────────────────────

   public function verifyEmail(VerifyEmailRequest $request): JsonResponse
{
    $validated = $request->validated();

    try {
        $this->accountRecoveryService->verifyEmail(
            (int)  $validated['user_id'],
            (string) $validated['code'],
            (bool) ($validated['is_token'] ?? false)
        );

        // Fetch the now‑verified user and issue a token
        $user  = $this->userService->getUserById((int) $validated['user_id']);
        $token = $user->generateAuthToken(); // Uses Laravel Sanctum / Passport

        return response()->json([
            'success'      => true,
            'code'         => 'EMAIL_VERIFIED',
            'message' => 'Identity confirmed. Authentication successful.',
            'user'         => new UserResource($user),
            'token'        => $token,
            'requires_mfa' => false,
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success'      => false,
            'code'         => 'VERIFICATION_FAILED',
            'message'      => $e->getMessage(),
            'user'         => null,
            'token'        => null,
            'requires_mfa' => false,
        ], $this->safeStatusCode($e->getCode(), 400));
    }
}

    /**
     * Re-send the email-verification notification.
     */
    public function resendVerification(ResendVerificationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->accountRecoveryService->sendEmailVerification(
                (int)    $validated['user_id'],
                (string) ($validated['channel'] ?? 'email'),
                ActionTypes::LOGIN_CONFIRMATION,
            );

            return response()->json([
                'success'      => true,
                'code'         => 'VERIFICATION_SENT',
                'message'      => $result['message'],
                'expires_at'   => $result['expires_at'],
                'user'         => null,
                'token'        => null,
                'requires_mfa' => false,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success'      => false,
                'code'         => 'RESEND_FAILED',
                'message'      => $e->getMessage(),
                'user'         => null,
                'token'        => null,
                'requires_mfa' => false,
            ], $this->safeStatusCode($e->getCode(), 400));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Password Reset
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Initiate a password reset (always responds with success to prevent enumeration).
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $this->accountRecoveryService->initiatePasswordReset(
                (string) $validated['email'],
                (string) ($validated['channel'] ?? 'email')
            );
        } catch (\Exception) {
            // Intentionally swallow — never reveal whether an email exists
        }

        // Always return the same response for security
        return response()->json([
            'success'      => true,
            'code'         => 'RESET_INITIATED',
            'message'      => 'If that email address is registered, a reset code has been sent.',
            'user'         => null,
            'token'        => null,
            'requires_mfa' => false,
        ]);
    }

    /**
     * Complete the password reset using a token or OTP.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $this->accountRecoveryService->resetPassword(
                (string) $validated['email'],
                (string) $validated['code'],
                (string) $validated['new_password'],
                (bool)   ($validated['is_token'] ?? false)
            );

            return response()->json([
                'success'      => true,
                'code'         => 'PASSWORD_RESET',
                'message'      => 'Password reset successfully. You may now log in with your new password.',
                'user'         => null,
                'token'        => null,
                'requires_mfa' => false,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success'      => false,
                'code'         => 'RESET_FAILED',
                'message'      => $e->getMessage(),
                'user'         => null,
                'token'        => null,
                'requires_mfa' => false,
            ], $this->safeStatusCode($e->getCode(), 400));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Session
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Logout the authenticated user (revoke all tokens).
     */
    public function logout(LogoutRequest $request): JsonResponse
    {
        $this->userService->logout($request->user());

        return response()->json([
            'success'      => true,
            'code'         => 'LOGOUT_SUCCESS',
            'message'      => 'Successfully logged out.',
            'user'         => null,
            'token'        => null,
            'requires_mfa' => false,
        ]);
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success'      => true,
            'code'         => 'USER_RETRIEVED',
            'message'      => 'User retrieved successfully.',
            'user'         => new UserResource($request->user()),
            'token'        => null,
            'requires_mfa' => false,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Map a registration exception to [httpStatus, errorCode, message].
     *
     * @return array{int, string, string}
     */
    private function resolveRegistrationError(\Exception $e): array
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'email already exists')) {
            return [409, 'EMAIL_ALREADY_REGISTERED', 'A user with this email already exists.'];
        }

        if (str_contains($msg, 'national ID already exists')) {
            return [409, 'NATIONAL_ID_ALREADY_REGISTERED', 'A user with this national ID already exists.'];
        }

        Log::error('Unexpected registration error', ['error' => $msg]);

        return [500, 'REGISTRATION_FAILED', 'Registration failed. Please try again later.'];
    }

    /**
     * Ensure an exception code maps to a valid HTTP status code.
     * Falls back to $default if the code is 0 or out of range.
     */
    private function safeStatusCode(int $code, int $default = 400): int
    {
        return ($code >= 400 && $code < 600) ? $code : $default;
    }
}
