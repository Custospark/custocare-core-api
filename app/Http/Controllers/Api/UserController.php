<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Services\User\Contracts\UserServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // for authorize()


class UserController extends Controller
{

    use AuthorizesRequests;

    /**
     * Constructor.
     *
     * @param UserServiceInterface $userService
     */
    public function __construct(
        private readonly UserServiceInterface $userService
    ) {
        $this->authorizeResource(\App\Models\User::class, 'user');
    }

    /**
     * Display a listing of users.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = $request->input('per_page', 15);
        $filters = $request->only(['identity_state', 'data_residency_region', 'national_id_country_code', 'search']);

        $users = $this->userService->getAllUsers($perPage, $filters);

        return UserResource::collection($users);
    }

    /**
     * Store a newly created user.
     *
     * @param StoreUserRequest $request
     * @return JsonResponse
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->createUser($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified user.
     *
     * @param string $uuid
     * @return UserResource
     */
    public function show(string $uuid): UserResource
    {
        $user = $this->userService->getUserByUuid($uuid);

        return new UserResource($user);
    }

    /**
     * Update the specified user.
     *
     * @param UpdateUserRequest $request
     * @param int $id
     * @return UserResource
     */
    public function update(UpdateUserRequest $request, int $id): UserResource
    {
        $user = $this->userService->updateUser($id, $request->validated());

        return new UserResource($user);
    }

    /**
     * Remove the specified user.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $this->userService->deleteUser($id);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Verify user identity.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        $this->authorize('verifyIdentity', \App\Models\User::class);

        $validated = $request->validate([
            'staff_id' => 'required|integer|exists:staff,id',
            'method' => 'required|string|max:50',
        ]);

        $user = $this->userService->verifyIdentity(
            $id,
            $validated['staff_id'],
            $validated['method']
        );

        return (new UserResource($user))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Suspend user.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function suspend(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);
        $this->authorize('suspend', $user);

        $user = $this->userService->suspendUser($id);

        return (new UserResource($user))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Restore suspended user.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restoreSuspended(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);
        $this->authorize('restoreFromSuspension', $user);

        $user = $this->userService->restoreUser($id);

        return (new UserResource($user))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Archive user.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function archive(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);
        $this->authorize('archive', $user);

        $user = $this->userService->archiveUser($id);

        return (new UserResource($user))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Get users pending identity verification.
     *
     * @return AnonymousResourceCollection
     */
    public function pendingVerification(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', \App\Models\User::class);

        $users = $this->userService->getPendingIdentityVerificationUsers();

        return UserResource::collection($users);
    }

    /**
     * Get users by data residency region.
     *
     * @param string $region
     * @return AnonymousResourceCollection
     */
    public function byRegion(string $region): AnonymousResourceCollection
    {
        $this->authorize('viewAny', \App\Models\User::class);

        $users = $this->userService->getUsersByDataResidencyRegion($region);

        return UserResource::collection($users);
    }
}