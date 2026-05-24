<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\FacilityOwner;
use App\Services\Billing\Contracts\AssignableModuleServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AssignableModuleController extends Controller
{
    public function __construct(
        private readonly AssignableModuleServiceInterface $assignableModuleService,
    ) {}

    /**
     * GET /api/facilities/{facility}/assignable-modules
     *
     * Returns modules scoped to the facility's subscription plan tier.
     */
    public function index(Facility $facility): JsonResponse
    {
        $staffId = Auth::user()?->staff?->id;
        $inviterIsOwner = $staffId
            ? FacilityOwner::query()
                ->where('facility_id', $facility->id)
                ->where('staff_id', $staffId)
                ->exists()
            : false;

        $payload = $this->assignableModuleService->getForFacility(
            (int) $facility->id,
            $inviterIsOwner,
        );

        return response()->json([
            'success' => true,
            'message' => 'Assignable modules retrieved.',
            'data' => array_merge($payload, [
                'editor_is_facility_owner' => $inviterIsOwner,
            ]),
        ]);
    }
}
