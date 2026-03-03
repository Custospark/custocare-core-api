<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\PlanResource;
use App\Models\Plan;
use App\Repositories\Billing\Contracts\PlanRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public read-only plan listing.
 * No auth required — facilities browse plans before subscribing.
 */
class PlanController extends Controller
{
    public function __construct(
        private readonly PlanRepositoryInterface $planRepo
    ) {}

    /**
     * GET /api/billing/plans
     * List all active subscription plans.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $plans = $this->planRepo->getAllActive();

        return PlanResource::collection($plans);
    }

    /**
     * GET /api/billing/plans/{plan}
     * Show a specific plan by ID or slug.
     */
    public function show(Plan $plan): JsonResponse
    {
        if (! $plan->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found.',
                'data'    => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Plan retrieved successfully.',
            'data'    => new PlanResource($plan),
        ]);
    }
}
