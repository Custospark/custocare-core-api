<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\UsageResource;
use App\Models\Facility;
use App\Services\Billing\Contracts\PlanLimitServiceInterface;
use App\Services\Billing\Contracts\UsageServiceInterface;
use Illuminate\Http\JsonResponse;

class UsageController extends Controller
{
    public function __construct(
        private readonly UsageServiceInterface $usageService,
        private readonly PlanLimitServiceInterface $planLimitService,
    ) {}

    /**
     * GET /api/facilities/{facility}/usage
     *
     * Returns current usage counts and plan limits for the facility.
     */
    public function index(Facility $facility): JsonResponse
    {
        $usage = $this->usageService->getAll($facility->id);
        $limits = $this->planLimitService->getPlanLimits($facility->id);

        return response()->json([
            'success' => true,
            'message' => 'Usage retrieved.',
            'data'    => new UsageResource(array_merge($usage, [
                'limits' => $limits,
            ])),
        ]);
    }
}
