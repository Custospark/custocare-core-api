<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NursingMedication\StoreNursingMedicationAdministrationRequest;
use App\Services\NursingMedication\NursingMedicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NursingMedicationAdministrationController extends Controller
{
    public function __construct(private NursingMedicationService $service) {}

    /** GET /api/nursing/medication-administrations — administer / audit trail */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'visit_id' => ['nullable', 'integer'],
            'outcome' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $facilityId = (int) $request->query('facility_id');
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $filters = array_filter([
            'visit_id' => $request->query('visit_id'),
            'outcome' => $request->query('outcome'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ], fn ($v) => $v !== null && $v !== '');

        $paginator = $this->service->paginateAdministrations($facilityId, $filters, $perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreNursingMedicationAdministrationRequest $request): JsonResponse
    {
        $actorId = (int) Auth::id();
        if ($actorId < 1) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $row = $this->service->recordAdministration($request->validated(), $actorId);

        return response()->json([
            'message' => 'Administration recorded.',
            'data' => $row,
        ], 201);
    }
}
