<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FacilityShiftHandover\StoreFacilityShiftHandoverRequest;
use App\Http\Requests\FacilityShiftHandover\UpdateFacilityShiftHandoverRequest;
use App\Services\FacilityShiftHandover\FacilityShiftHandoverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FacilityShiftHandoverController extends Controller
{
    public function __construct(private FacilityShiftHandoverService $service) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'ward_id' => ['nullable', 'integer'],
            'shift_date' => ['nullable', 'date'],
            'shift_date_from' => ['nullable', 'date'],
            'shift_date_to' => ['nullable', 'date'],
            'status' => ['nullable', 'string'],
            'shift_slot' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $facilityId = (int) $request->query('facility_id');
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $filters = array_filter([
            'ward_id' => $request->query('ward_id'),
            'shift_date' => $request->query('shift_date'),
            'shift_date_from' => $request->query('shift_date_from'),
            'shift_date_to' => $request->query('shift_date_to'),
            'status' => $request->query('status'),
            'shift_slot' => $request->query('shift_slot'),
        ], fn ($v) => $v !== null && $v !== '');

        $paginator = $this->service->paginateIndex($facilityId, $filters, $perPage);

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

    public function store(StoreFacilityShiftHandoverRequest $request): JsonResponse
    {
        $handover = $this->service->create($request->validated(), (int) Auth::id());

        return response()->json([
            'message' => 'Shift handover saved successfully.',
            'data' => $handover,
        ], 201);
    }

    public function show(Request $request, int $handover): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
        ]);

        $facilityId = (int) $request->query('facility_id');
        $model = $this->service->findWithRelations($handover, $facilityId);

        if (! $model) {
            return response()->json([
                'message' => 'Shift handover not found for this facility.',
            ], 404);
        }

        return response()->json(['data' => $model]);
    }

    public function update(UpdateFacilityShiftHandoverRequest $request, int $handover): JsonResponse
    {
        $facilityId = (int) $request->validated()['facility_id'];

        $model = $this->service->findForFacility($handover, $facilityId);
        if (! $model) {
            return response()->json([
                'message' => 'Shift handover not found for this facility.',
            ], 404);
        }

        $payload = collect($request->validated())->except('facility_id')->all();

        $updated = $this->service->update($model, $payload, (int) Auth::id());

        return response()->json([
            'message' => 'Shift handover updated successfully.',
            'data' => $updated,
        ]);
    }
}
