<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NursingMedication\StoreNursingTreatmentLogRequest;
use App\Services\NursingMedication\NursingMedicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NursingTreatmentLogController extends Controller
{
    public function __construct(private NursingMedicationService $service) {}

    /** GET /api/nursing/treatment-logs */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'visit_id' => ['nullable', 'integer'],
            'category' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $facilityId = (int) $request->query('facility_id');
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $filters = array_filter([
            'visit_id' => $request->query('visit_id'),
            'category' => $request->query('category'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ], fn ($v) => $v !== null && $v !== '');

        $paginator = $this->service->paginateTreatmentLogs($facilityId, $filters, $perPage);

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

    public function store(StoreNursingTreatmentLogRequest $request): JsonResponse
    {
        $actorId = (int) Auth::id();
        if ($actorId < 1) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $log = $this->service->createTreatmentLog($request->validated(), $actorId);

        return response()->json([
            'message' => 'Treatment log entry created.',
            'data' => $log,
        ], 201);
    }
}
