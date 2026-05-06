<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ward\StoreWardRequest;
use App\Http\Requests\Ward\UpdateWardRequest;
use App\Models\Ward;
use App\Models\WardBed;
use App\Services\Ward\WardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class WardController extends Controller
{
    public function __construct(private WardService $service) {}

    private function wardNotFoundForFacilityJson(): JsonResponse
    {
        return response()->json([
            'message' => 'Ward not found for this facility.',
            'errors' => [
                'ward_id' => ['The ward does not exist, was removed, or does not belong to this facility.'],
            ],
        ], 404);
    }

    // GET /api/wards?facility_id=1&status=active&ward_type=medical&search=med
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'status' => ['nullable', 'in:active,inactive,temporarily_closed'],
            'ward_type' => ['nullable', 'in:medical,surgical,maternity,pediatric,icu,nicu,psychiatric,isolation,emergency_observation,general'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $facilityId = (int) $request->query('facility_id');

        $data = $this->service->list($facilityId, [
            'status' => $request->query('status'),
            'ward_type' => $request->query('ward_type'),
            'search' => $request->query('search'),
        ]);

        return response()->json(['data' => $data]);
    }

    // POST /api/wards
    public function store(StoreWardRequest $request): JsonResponse
    {
        $user = Auth::user();

        $ward = $this->service->create($request->validated(), $user->id);

        return response()->json([
            'message' => 'Ward created successfully.',
            'data' => $ward,
        ], 201);
    }

    // GET /api/wards/{ward}?facility_id=1
    public function show(Request $request, int $ward): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
        ]);

        $facilityId = (int) $request->query('facility_id');
        $wardModel = $this->service->findForFacility($ward, $facilityId);
        if (!$wardModel) {
            return $this->wardNotFoundForFacilityJson();
        }

        return response()->json(['data' => $wardModel]);
    }

    // PATCH /api/wards/{ward}?facility_id=1
    public function update(UpdateWardRequest $request, int $ward): JsonResponse
    {
        $facilityId = (int) $request->query('facility_id', 0);
        if ($facilityId === 0) {
            $fallback = Ward::query()->whereKey($ward)->first();
            if (!$fallback) {
                return $this->wardNotFoundForFacilityJson();
            }
            $facilityId = (int) $fallback->facility_id;
        }

        $wardModel = $this->service->findForFacility($ward, $facilityId);
        if (!$wardModel) {
            return $this->wardNotFoundForFacilityJson();
        }

        $wardModel = $this->service->update($wardModel, $request->validated(), Auth::id());

        return response()->json([
            'message' => 'Ward updated successfully.',
            'data' => $wardModel,
        ]);
    }

    // DELETE /api/wards/{ward}?facility_id=1
    public function destroy(Request $request, int $ward): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
        ]);

        $facilityId = (int) $request->query('facility_id');
        $wardModel = $this->service->findForFacility($ward, $facilityId);
        if (!$wardModel) {
            return $this->wardNotFoundForFacilityJson();
        }

        $this->service->delete($wardModel);

        return response()->json([
            'message' => 'Ward deleted successfully.',
        ]);
    }

    // GET /api/wards/{ward}/beds?facility_id=1
    public function beds(Request $request, int $ward): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
        ]);

        $facilityId = (int) $request->query('facility_id');
        $wardModel = $this->service->findForFacility($ward, $facilityId);
        if (!$wardModel) {
            return $this->wardNotFoundForFacilityJson();
        }

        $beds = WardBed::query()
            ->where('ward_id', $wardModel->id)
            ->when(Schema::hasColumn('ward_beds', 'room_label'), fn ($q) => $q->orderByRaw('COALESCE(room_label, "")'))
            ->orderBy('bed_label')
            ->get();

        return response()->json([
            'data' => $beds,
        ]);
    }

    // POST /api/wards/{ward}/beds
    public function storeBed(Request $request, int $ward): JsonResponse
    {
        try {
            $hasRoomLabel = Schema::hasColumn('ward_beds', 'room_label');
            $rules = [
                'facility_id' => ['required', 'integer', 'exists:facilities,id'],
                'bed_label' => ['required', 'string', 'max:50'],
                'status' => ['nullable', 'in:available,occupied,maintenance,inactive'],
                'note' => ['nullable', 'string'],
            ];
            if ($hasRoomLabel) {
                $rules['room_label'] = ['nullable', 'string', 'max:50'];
            }
            $validated = $request->validate($rules);

            $facilityId = (int) $validated['facility_id'];
            $wardModel = $this->service->findForFacility($ward, $facilityId);
            if (!$wardModel) {
                return $this->wardNotFoundForFacilityJson();
            }

            $payload = [
                'facility_id' => $facilityId,
                'ward_id' => $wardModel->id,
                'bed_label' => trim($validated['bed_label']),
                'status' => $validated['status'] ?? 'available',
                'note' => $validated['note'] ?? null,
                'created_by_user_id' => Auth::id(),
                'updated_by_user_id' => Auth::id(),
            ];
            if ($hasRoomLabel) {
                $payload['room_label'] = isset($validated['room_label']) ? trim((string) $validated['room_label']) : null;
            }
            $bed = WardBed::create($payload);

            return response()->json([
                'message' => 'Bed created successfully.',
                'data' => $bed,
            ], 201);
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return response()->json([
                    'message' => 'A bed with this label already exists in the selected ward.',
                    'errors' => ['bed_label' => ['Duplicate bed label in ward.']],
                ], 422);
            }
            throw $e;
        }
    }

    // PATCH /api/ward-beds/{bed}
    public function updateBed(Request $request, WardBed $bed): JsonResponse
    {
        try {
            $hasRoomLabel = Schema::hasColumn('ward_beds', 'room_label');
            $rules = [
                'facility_id' => ['required', 'integer', 'exists:facilities,id'],
                'ward_id' => ['sometimes', 'integer', 'exists:wards,id'],
                'bed_label' => ['sometimes', 'string', 'max:50'],
                'status' => ['sometimes', 'in:available,occupied,maintenance,inactive'],
                'note' => ['sometimes', 'nullable', 'string'],
            ];
            if ($hasRoomLabel) {
                $rules['room_label'] = ['sometimes', 'nullable', 'string', 'max:50'];
            }
            $validated = $request->validate($rules);

            $facilityId = (int) $validated['facility_id'];
            if ((int) $bed->facility_id !== $facilityId) {
                return response()->json([
                    'message' => 'Facility scope mismatch.',
                ], 422);
            }

            $payload = [
                'ward_id' => $validated['ward_id'] ?? $bed->ward_id,
                'bed_label' => isset($validated['bed_label']) ? trim($validated['bed_label']) : $bed->bed_label,
                'status' => $validated['status'] ?? $bed->status,
                'note' => array_key_exists('note', $validated) ? $validated['note'] : $bed->note,
                'updated_by_user_id' => Auth::id(),
            ];
            if ($hasRoomLabel) {
                $payload['room_label'] = array_key_exists('room_label', $validated)
                    ? trim((string) ($validated['room_label'] ?? '')) ?: null
                    : $bed->room_label;
            }
            $bed->update($payload);

            return response()->json([
                'message' => 'Bed updated successfully.',
                'data' => $bed->fresh(),
            ]);
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return response()->json([
                    'message' => 'A bed with this label already exists in the selected ward.',
                    'errors' => ['bed_label' => ['Duplicate bed label in ward.']],
                ], 422);
            }
            throw $e;
        }
    }
}
