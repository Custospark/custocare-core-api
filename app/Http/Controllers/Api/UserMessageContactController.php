<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MessageContact\StoreUserMessageContactRequest;
use App\Http\Requests\MessageContact\UpdateUserMessageContactRequest;
use App\Services\Contracts\UserMessageContactServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class UserMessageContactController extends Controller
{
    public function __construct(
        private readonly UserMessageContactServiceInterface $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'   => 'nullable|string|max:200',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $ownerId = (int) Auth::id();
        $paginator = $this->service->listForOwner(
            $ownerId,
            $validated['search'] ?? null,
            (int) ($validated['per_page'] ?? 20),
        );

        return response()->json([
            'success' => true,
            'message' => 'Message contacts retrieved.',
            'data'    => collect($paginator->items())->map(
                fn ($contact) => $this->service->serializeForOwner($contact),
            )->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $ownerId = (int) Auth::id();
        $contact = $this->service->getForOwner($ownerId, $id);

        return response()->json([
            'success' => true,
            'message' => 'Message contact retrieved.',
            'data'    => $this->service->serializeForOwner($contact),
        ]);
    }

    public function store(StoreUserMessageContactRequest $request): JsonResponse
    {
        $ownerId = (int) Auth::id();

        try {
            $contact = $this->service->createForOwner($ownerId, $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors'  => ['contact' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact saved to your notebook.',
            'data'    => $this->service->serializeForOwner($contact),
        ], 201);
    }

    public function update(UpdateUserMessageContactRequest $request, int $id): JsonResponse
    {
        $ownerId = (int) Auth::id();

        try {
            $contact = $this->service->updateForOwner($ownerId, $id, $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors'  => ['contact' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact updated.',
            'data'    => $this->service->serializeForOwner($contact),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $ownerId = (int) Auth::id();
        $this->service->deleteForOwner($ownerId, $id);

        return response()->json([
            'success' => true,
            'message' => 'Contact removed from your notebook.',
            'data'    => null,
        ]);
    }

    public function resolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
        ]);

        try {
            $result = $this->service->resolveChannel(
                $validated['email'] ?? null,
                $validated['phone'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors'  => ['contact' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact resolved.',
            'data'    => $result,
        ]);
    }

    public function touch(int $id): JsonResponse
    {
        $ownerId = (int) Auth::id();
        $contact = $this->service->touchLastUsed($ownerId, $id);

        return response()->json([
            'success' => true,
            'message' => 'Contact usage updated.',
            'data'    => $this->service->serializeForOwner($contact),
        ]);
    }
}
