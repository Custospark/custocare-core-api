<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\LoginResource;
use App\Http\Resources\UserResource;
use App\Services\User\Contracts\UserServiceInterface;
use App\Services\User\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param UserServiceInterface $userService
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
        dd("Here");
        $user = $this->userService->register($request->validated());

        return response()->json([
            'message' => 'User registered successfully',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Login user.
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only(['email', 'password']);
        $ip = $request->ip();
        $userAgent = $request->userAgent();

        $result = $this->userService->login($credentials, $ip, $userAgent);

        // Handle MFA if required
        if ($result['requires_mfa'] && !$request->has('mfa_code')) {
            return response()->json([
                'message' => 'MFA required',
                'requires_mfa' => true,
            ], 200);
        }

        // Validate MFA code if provided
        if ($result['requires_mfa'] && $request->has('mfa_code')) {
            $valid = $this->userService->validateMfa(
                $result['user']->id,
                $request->input('mfa_code')
            );

            if (!$valid) {
                return response()->json([
                    'message' => 'Invalid MFA code',
                ], 401);
            }
        }

        return response()->json(new LoginResource($result));
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
            'message' => 'Successfully logged out',
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
        return response()->json(new UserResource($request->user()));
    }
}