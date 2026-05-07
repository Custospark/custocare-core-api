<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NursingMedication\StoreNursingMedicationDoseRequest;
use App\Http\Requests\NursingMedication\UpdateNursingMedicationDoseRequest;
use App\Models\NursingMedicationDose;
use App\Services\NursingMedication\NursingMedicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NursingMedicationDoseController extends Controller
{
    public function __construct(private NursingMedicationService $service) {}

    /** GET /api/nursing/medication-doses — medication schedule / MAR board */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'visit_id' => ['nullable', 'integer'],
            'ward_id' => ['nullable', 'integer'],
            'patient_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $facilityId = (int) $request->query('facility_id');
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $filters = array_filter([
            'visit_id' => $request->query('visit_id'),
            'ward_id' => $request->query('ward_id'),
            'patient_id' => $request->query('patient_id'),
            'status' => $request->query('status'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ], fn ($v) => $v !== null && $v !== '');

        $paginator = $this->service->paginateDoses($facilityId, $filters, $perPage);

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

    /** GET /api/nursing/medication-doses/missed — overdue pending doses */
    public function missed(Request $request): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'visit_id' => ['nullable', 'integer'],
            'ward_id' => ['nullable', 'integer'],
            'as_of' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $facilityId = (int) $request->query('facility_id');
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $filters = array_filter([
            'visit_id' => $request->query('visit_id'),
            'ward_id' => $request->query('ward_id'),
            'as_of' => $request->query('as_of'),
        ], fn ($v) => $v !== null && $v !== '');

        $paginator = $this->service->paginateMissedDoses($facilityId, $filters, $perPage);

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

    public function store(StoreNursingMedicationDoseRequest $request): JsonResponse
    {
        $dose = $this->service->createDose($request->validated(), Auth::id() ? (int) Auth::id() : null);

        return response()->json([
            'message' => 'Medication dose scheduled.',
            'data' => $dose,
        ], 201);
    }

    public function update(UpdateNursingMedicationDoseRequest $request, int $dose): JsonResponse
    {
        $facilityId = (int) $request->validated()['facility_id'];

        $model = NursingMedicationDose::query()
            ->whereKey($dose)
            ->where('facility_id', $facilityId)
            ->first();

        if (! $model) {
            return response()->json([
                'message' => 'Dose not found for this facility.',
            ], 404);
        }

        $payload = collect($request->validated())->except('facility_id')->all();

        $updated = $this->service->updateDose($model, $payload);

        return response()->json([
            'message' => 'Medication dose updated.',
            'data' => $updated,
        ]);
    }
}
