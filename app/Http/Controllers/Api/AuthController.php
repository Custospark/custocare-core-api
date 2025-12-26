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
        Log::info($request);
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

    $result = $this->userService->login(
        $credentials,
        $request->ip(),
        $request->userAgent()
    );

    Log::info('Login attempt', [
        'email' => $credentials['email'],
        'code' => $result['code'],
    ]);

    // Failure
    if (!$result['ok']) {
        return response()->json([
            'message' => $result['message'],
            'code' => $result['code'],
        ], $result['http']);
    }

    $data = $result['payload'];

    // MFA required but not provided
    if ($data['requires_mfa'] && !$request->filled('mfa_code')) {
        return response()->json([
            'message' => 'MFA required',
            'requires_mfa' => true,
        ], 200);
    }

    // MFA validation
    if ($data['requires_mfa']) {
        $valid = $this->userService->validateMfa(
            $data['user']->id,
            $request->input('mfa_code')
        );

        if (!$valid) {
            return response()->json([
                'message' => 'Invalid MFA code',
                'code' => 'INVALID_MFA',
            ], 401);
        }
    }

    return response()->json(
        new LoginResource($data),
        200
    );
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