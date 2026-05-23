<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\UsageResource;
use App\Models\Facility;
use App\Services\Billing\Contracts\UsageServiceInterface;
use Illuminate\Http\JsonResponse;

class UsageController extends Controller
{
    public function __construct(
        private readonly UsageServiceInterface $usageService
    ) {}

    /**
     * GET /api/facilities/{facility}/usage
     *
     * Returns current usage counts for the facility:
     * - staff (active + on_leave, distinct staff_id from facility_staff_roles)
     * - departments (active status)
     * - visits (distinct patients in last 30 days)
     */
    public function index(Facility $facility): JsonResponse
    {
        $usage = $this->usageService->getAll($facility->id);

        return response()->json([
            'success' => true,
            'message' => 'Usage retrieved.',
            'data'    => new UsageResource($usage),
        ]);
    }
}
