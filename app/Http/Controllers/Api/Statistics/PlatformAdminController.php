<?php

namespace App\Http\Controllers\Api\Statistics;

use App\Http\Controllers\Controller;
use App\Services\Statistics\PlatformAdminService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PlatformAdminController extends Controller
{
    protected PlatformAdminService $platformService;

    public function __construct(PlatformAdminService $platformService)
    {
        $this->platformService = $platformService;
    }

    /**
     * Resolve date range and also extract a reference date for summary counts.
     *
     * @return array{dateRange: array{from: Carbon|null, to: Carbon|null}, referenceDate: Carbon|null}
     */
    protected function resolveDateRangeAndReference(array $filters): array
    {
        $now = now();

        // Handle period filters
        if (!empty($filters['period'])) {
            return match ($filters['period']) {
                'today' => [
                    'dateRange' => ['from' => $now->copy()->startOfDay(), 'to' => $now->copy()->endOfDay()],
                    'referenceDate' => $now,
                ],
                'this_week' => [
                    'dateRange' => ['from' => $now->copy()->startOfWeek(), 'to' => $now->copy()->endOfWeek()],
                    'referenceDate' => $now,
                ],
                'this_month' => [
                    'dateRange' => ['from' => $now->copy()->startOfMonth(), 'to' => $now->copy()->endOfMonth()],
                    'referenceDate' => $now,
                ],
                default => [
                    'dateRange' => ['from' => null, 'to' => null],
                    'referenceDate' => null,
                ],
            };
        }

        // Handle single date filter
        if (!empty($filters['date'])) {
            $date = Carbon::parse($filters['date']);
            return [
                'dateRange' => ['from' => $date->copy()->startOfDay(), 'to' => $date->copy()->endOfDay()],
                'referenceDate' => $date,
            ];
        }

        // Handle custom date range
        $from = isset($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : null;
        $to   = isset($filters['date_to'])   ? Carbon::parse($filters['date_to'])->endOfDay()   : null;

        // If the range is exactly one day, use that day as reference date
        $referenceDate = null;
        if ($from && $to && $from->copy()->startOfDay()->eq($to->copy()->startOfDay())) {
            $referenceDate = $from;
        }

        return [
            'dateRange' => ['from' => $from, 'to' => $to],
            'referenceDate' => $referenceDate,
        ];
    }

    // -------------------------------------------------------------------------
    // FACILITIES
    // -------------------------------------------------------------------------

    public function listFacilities(Request $request): JsonResponse
    {
        try {
            $filters = $request->validate([
                'date_from'          => ['nullable', 'date'],
                'date_to'            => ['nullable', 'date', 'after_or_equal:date_from'],
                'date'               => ['nullable', 'date'],
                'period'             => ['nullable', 'in:today,this_week,this_month'],
                'per_page'           => ['nullable', 'integer', 'min:1', 'max:120'],
                'page'               => ['nullable', 'integer', 'min:1'],
                'status'             => ['nullable', 'in:active,suspended,banned'],
                'operational_status' => ['nullable', 'in:fully_operational,limited_services,emergency_only,temporarily_closed,permanently_closed,under_construction'],
                'search'             => ['nullable', 'string', 'max:100'],
            ]);

            $resolved = $this->resolveDateRangeAndReference($filters);
            $dateRange = $resolved['dateRange'];
            $referenceDate = $resolved['referenceDate'];

            $result = $this->platformService->getFacilitiesList(
                $dateRange,
                $filters['status'] ?? null,
                $filters['operational_status'] ?? null,
                $filters['search'] ?? null,
                (int) ($filters['per_page'] ?? 15),
                (int) ($filters['page'] ?? 1)
            );

            return response()->json([
                'success' => true,
                'data'    => $result['data'],
                'meta'    => [
                    'current_page'    => $result['current_page'],
                    'per_page'        => $result['per_page'],
                    'total'           => $result['total'],
                    'last_page'       => $result['last_page'],
                    'staff_counts'    => $this->platformService->getTotalStaffCount($dateRange),
                    'facility_counts' => $this->platformService->getFacilityCountsByDateRanges($referenceDate),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('PlatformAdminController@listFacilities failed.', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Server error.'], 500);
        }
    }

    public function updateFacilityStatus(Request $request, int $facilityId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status'        => ['required', 'in:active,suspended,banned'],
                'status_reason' => ['nullable', 'string', 'max:500'],
            ]);

            $adminUser = $request->user();
            if (!$adminUser) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $updated = $this->platformService->updateFacilityStatus(
                $facilityId,
                $validated['status'],
                $validated['status_reason'] ?? null,
                $adminUser->id
            );

            if (!$updated) {
                return response()->json(['success' => false, 'message' => 'Facility not found.'], 404);
            }

            return response()->json(['success' => true, 'message' => 'Facility status updated.']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('PlatformAdminController@updateFacilityStatus failed.', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Server error.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // USERS
    // -------------------------------------------------------------------------

    public function listUsers(Request $request): JsonResponse
    {
        try {
            $filters = $request->validate([
                'date_from' => ['nullable', 'date'],
                'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
                'date'      => ['nullable', 'date'],
                'period'    => ['nullable', 'in:today,this_week,this_month'],
                'per_page'  => ['nullable', 'integer', 'min:1', 'max:120'],
                'page'      => ['nullable', 'integer', 'min:1'],
                'status'    => ['nullable', 'in:active,suspended,banned'],
                'search'    => ['nullable', 'string', 'max:100'],
            ]);

            $resolved = $this->resolveDateRangeAndReference($filters);
            $dateRange = $resolved['dateRange'];
            $referenceDate = $resolved['referenceDate'];

            $result = $this->platformService->getUsersList(
                $dateRange,
                $filters['status'] ?? null,
                $filters['search'] ?? null,
                (int) ($filters['per_page'] ?? 15),
                (int) ($filters['page'] ?? 1)
            );

            return response()->json([
                'success' => true,
                'data'    => $result['data'],
                'meta'    => [
                    'current_page' => $result['current_page'],
                    'per_page'     => $result['per_page'],
                    'total'        => $result['total'],
                    'last_page'    => $result['last_page'],
                    'user_counts'  => $this->platformService->getUserCountsByDateRanges($referenceDate),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('PlatformAdminController@listUsers failed.', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Server error.'], 500);
        }
    }

    public function updateUserStatus(Request $request, int $userId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status'        => ['required', 'in:active,suspended,banned'],
                'status_reason' => ['nullable', 'string', 'max:500'],
            ]);

            $adminUser = $request->user();
            if (!$adminUser) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $updated = $this->platformService->updateUserStatus(
                $userId,
                $validated['status'],
                $validated['status_reason'] ?? null,
                $adminUser->id
            );

            if (!$updated) {
                return response()->json(['success' => false, 'message' => 'User not found.'], 404);
            }

            return response()->json(['success' => true, 'message' => 'User status updated.']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('PlatformAdminController@updateUserStatus failed.', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Server error.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // PATIENTS
    // -------------------------------------------------------------------------

    public function listPatients(Request $request): JsonResponse
    {
        try {
            $filters = $request->validate([
                'date_from' => ['nullable', 'date'],
                'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
                'date'      => ['nullable', 'date'],
                'period'    => ['nullable', 'in:today,this_week,this_month'],
                'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
                'page'      => ['nullable', 'integer', 'min:1'],
                'status'    => ['nullable', 'in:active,inactive,deceased,merged,test_patient,system_patient'],
                'search'    => ['nullable', 'string', 'max:100'],
            ]);

            $resolved = $this->resolveDateRangeAndReference($filters);
            $dateRange = $resolved['dateRange'];
            $referenceDate = $resolved['referenceDate'];

            $result = $this->platformService->getPatientsList(
                $dateRange,
                $filters['status'] ?? null,
                $filters['search'] ?? null,
                (int) ($filters['per_page'] ?? 15),
                (int) ($filters['page'] ?? 1)
            );

            return response()->json([
                'success' => true,
                'data'    => $result['data'],
                'meta'    => [
                    'current_page' => $result['current_page'],
                    'per_page'     => $result['per_page'],
                    'total'        => $result['total'],
                    'last_page'    => $result['last_page'],
                    'counts'       => $this->platformService->getPatientCountsByDateRanges($referenceDate),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('PlatformAdminController@listPatients failed.', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Server error.'], 500);
        }
    }
}