<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\Admin\StorePlanRequest;
use App\Http\Requests\Billing\Admin\UpdatePlanRequest;
use App\Http\Resources\Billing\PlanResource;
use App\Models\Plan;
use App\Repositories\Billing\Contracts\PlanRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Admin full CRUD for subscription plans. */
class PlanController extends Controller
{
    public function __construct(
        private readonly PlanRepositoryInterface $planRepo
    ) {}

    /**
     * GET /api/admin/billing/plans
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['is_active', 'search']);
        $perPage = $request->integer('per_page', 15);

        $plans = $this->planRepo->getAllPaginated($filters, $perPage);

        return PlanResource::collection($plans);
    }

    /**
     * POST /api/admin/billing/plans
     */
    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = $this->planRepo->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Plan created successfully.',
            'data'    => new PlanResource($plan),
        ], 201);
    }

    /**
     * GET /api/admin/billing/plans/{plan}
     */
    public function show(Plan $plan): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Plan retrieved.',
            'data'    => new PlanResource($plan),
        ]);
    }

    /**
     * PUT /api/admin/billing/plans/{plan}
     */
    public function update(UpdatePlanRequest $request, Plan $plan): JsonResponse
    {
        $updated = $this->planRepo->update($plan, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Plan updated successfully.',
            'data'    => new PlanResource($updated),
        ]);
    }

    /**
     * DELETE /api/admin/billing/plans/{plan}
     */
    public function destroy(Plan $plan): JsonResponse
    {
        // Prevent deletion if facilities are subscribed to this plan
        if ($plan->subscriptions()->whereNotIn('status', ['cancelled'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a plan with active subscriptions. Deactivate it instead.',
                'data'    => null,
            ], 422);
        }

        $this->planRepo->delete($plan);

        return response()->json([
            'success' => true,
            'message' => 'Plan deleted successfully.',
            'data'    => null,
        ]);
    }
}
