<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\LoginResource;
use App\Http\Resources\UserResource;
use App\Services\User\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param UserService $userService
     */
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Register a new user.
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        Log::info('User registration attempt', ['email' => $request->input('email')]);
        
        try {
            $user = $this->userService->register($request->validated());
            
            // Create token for immediate login after registration
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'code' => 'REGISTRATION_SUCCESS',
                'message' => 'User registered successfully',
                'user' => new UserResource($user),
                'token' => $token,
                'requires_mfa' => false,
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'email' => $request->input('email')
            ]);
            
            return response()->json([
                'success' => false,
                'code' => 'REGISTRATION_FAILED',
                'message' => 'Registration failed. Please try again.',
                'user' => null,
                'token' => null,
                'requires_mfa' => false,
            ], 500);
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

        // Always return a consistent JSON structure
        $responseData = [
            'success' => $result['success'],
            'code' => $result['code'],
            'message' => $result['message'],
            'requires_mfa' => $result['requires_mfa'],
            'user' => $result['user'] ? new UserResource($result['user']) : null,
            'token' => $result['token'],
        ];

        // Determine HTTP status code based on result
        $statusCode = match($result['code']) {
            'LOGIN_SUCCESS' => 200,
            'MFA_REQUIRED' => 200, // Still 200 since this is a valid response
            'ACCOUNT_LOCKED' => 423,
            'INVALID_CREDENTIALS', 'INVALID_MFA' => 401,
            default => 400,
        };

        return response()->json($responseData, $statusCode);
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