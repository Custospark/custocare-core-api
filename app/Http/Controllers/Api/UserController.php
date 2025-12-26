<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Services\Contracts\UserServiceInterface;
use App\Services\User\Contracts\UserServiceInterface as ContractsUserServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param UserServiceInterface $userService
     */
    public function __construct(
        private readonly ContractsUserServiceInterface $userService
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['identity_state', 'data_residency_region', 'search']);
        $perPage = $request->input('per_page', 20);

        $users = $this->userService->getAllUsers($filters, $perPage);

        return UserResource::collection($users);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreUserRequest $request
     * @return JsonResponse
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->createUser($request->validated());

        return response()->json([
            'message' => 'User created successfully',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return UserResource
     */
    public function show(int $id): UserResource
    {
        $user = $this->userService->getUserById($id);
        return new UserResource($user);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateUserRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = $this->userService->updateUser($id, $request->validated());

        return response()->json([
            'message' => 'User updated successfully',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $this->userService->deleteUser($id);

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Restore the specified soft-deleted resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        $user = $this->userService->restoreUser($id);

        return response()->json([
            'message' => 'User restored successfully',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Verify user identity.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function verifyIdentity(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'method' => 'required|string|in:passport,biometric,government_id,other',
        ]);

        $staffId = $request->user()->id;
        $method = $request->input('method');

        $user = $this->userService->verifyIdentity($id, $staffId, $method);

        return response()->json([
            'message' => 'User identity verified successfully',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update user password.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updatePassword(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'current_password' => 'sometimes|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $currentPassword = $request->input('current_password');
        $newPassword = $request->input('new_password');

        $this->userService->updatePassword($id, $newPassword, $currentPassword);

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * Enable MFA for user.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function enableMfa(int $id): JsonResponse
    {
        $result = $this->userService->enableMfa($id);

        return response()->json([
            'message' => 'MFA enabled successfully',
            'secret' => $result['secret'],
            'qr_code_url' => $result['qr_code_url'],
        ]);
    }

    /**
     * Disable MFA for user.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function disableMfa(int $id): JsonResponse
    {
        $this->userService->disableMfa($id);

        return response()->json([
            'message' => 'MFA disabled successfully',
        ]);
    }
}