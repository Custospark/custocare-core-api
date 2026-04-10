<?php

namespace App\Services\Statistics;

use App\Services\Billing\Analytics\BillingRevenueDashboardService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FacilityOwnerAnalyticsService
{
    protected BillingRevenueDashboardService $billingService;

    public function __construct(BillingRevenueDashboardService $billingService)
    {
        $this->billingService = $billingService;
    }

    public function getDashboard(int $facilityId, array $filters = []): array
    {
        try {
            $normalized = $this->normalizeFilters($filters);

            $data = [
                'staff_availability'   => $this->buildStaffMetrics($facilityId, $normalized),
                'capacity_utilization' => $this->buildCapacityMetrics($facilityId),
                'inventory_risk'       => $this->buildInventoryMetrics($facilityId),
                'service_pricing'      => $this->buildServiceMetrics($facilityId, $normalized['top']),
                'financial'            => $this->buildFinancialMetrics($facilityId, $normalized),
            ];

            return [
                'success' => true,
                'message' => 'Operational decisions dashboard generated successfully.',
                'data' => $data,
                'filters' => [
                    'facility_id' => $facilityId,
                    'date_from' => $normalized['date_from']->toDateString(),
                    'date_to' => $normalized['date_to']->toDateString(),
                    'group_by' => $normalized['group_by'],
                    'top' => $normalized['top'],
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Facility owner analytics generation failed.', [
                'facility_id' => $facilityId,
                'filters' => $filters,
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to generate operational dashboard.',
                'errors' => [
                    'system' => ['An unexpected error occurred while generating the dashboard.'],
                ],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * ---------------------------------------------------------------------
     * STAFF AVAILABILITY / WORKLOAD
     * ---------------------------------------------------------------------
     *
     * Rules:
     * 1. Snapshot is always resolved as of date_to.
     * 2. "week" means a rolling 7-day window inclusive of today/date_to.
     * 3. "month" means a rolling 30-day window inclusive of today/date_to.
     * 4. Presence trend resolves latest known per-staff status at each bucket end.
     * 5. Only staff active at the relevant point are counted for that point.
     */
    protected function buildStaffMetrics(int $facilityId, array $normalized): array
    {
        $dateFrom = $normalized['date_from'];
        $dateTo   = $normalized['date_to'];
        $groupBy  = $normalized['group_by'];
        $top      = $normalized['top'];

        $assignments = $this->getFacilityStaffAssignments($facilityId, $dateFrom, $dateTo);

        $allStaffIds = $assignments
            ->pluck('staff_id')
            ->unique()
            ->values();

        if ($allStaffIds->isEmpty()) {
            return [
                'current_snapshot' => [
                    'staff_on_duty' => 0,
                    'staff_busy' => 0,
                    'staff_off_duty' => 0,
                    'total_active' => 0,
                    'occupancy_rate' => 0.0,
                ],
                'role_distribution' => [],
                'high_workload_staff' => [],
                'presence_trend' => $this->emptyPresenceTrend($dateFrom, $dateTo, $groupBy),
                'workload_trend' => $this->emptyWorkloadTrend($dateFrom, $dateTo, $groupBy),
            ];
        }

        $staffDirectory = $this->getStaffDirectory($allStaffIds->all());

        $presenceEvents = $this->getStaffPresenceEvents(
            $facilityId,
            $allStaffIds->all(),
            $dateTo
        );

        $presenceByStaff = $presenceEvents
            ->groupBy('staff_id')
            ->map(fn (Collection $rows) => $rows->sortBy('event_at')->values());

        $staffIdsAtDateTo = $this->getStaffIdsActiveAtPoint($assignments, $dateTo);

        $snapshotCounts = [
            'on_duty' => 0,
            'busy' => 0,
            'off_duty' => 0,
        ];

        foreach ($staffIdsAtDateTo as $staffId) {
            $status = $this->resolvePresenceStatusAt(
                $presenceByStaff->get($staffId, collect()),
                $dateTo
            );

            $snapshotCounts[$status]++;
        }

        $totalCurrentlyActive = $snapshotCounts['on_duty'] + $snapshotCounts['busy'];

        $currentSnapshot = [
            'staff_on_duty' => $snapshotCounts['on_duty'],
            'staff_busy' => $snapshotCounts['busy'],
            'staff_off_duty' => $snapshotCounts['off_duty'],
            'total_active' => $totalCurrentlyActive,
            'occupancy_rate' => $totalCurrentlyActive > 0
                ? round(($snapshotCounts['busy'] / $totalCurrentlyActive) * 100, 2)
                : 0.0,
        ];

        $roleDistribution = $assignments
            ->filter(function (array $assignment) use ($dateTo) {
                $startsOk = empty($assignment['effective_from']) || $assignment['effective_from']->lte($dateTo);
                $endsOk = empty($assignment['effective_to']) || $assignment['effective_to']->gte($dateTo);

                return $startsOk && $endsOk;
            })
            ->groupBy('role_code')
            ->map(function (Collection $rows, string $roleCode) {
                return [
                    'role' => $roleCode,
                    'count' => $rows->pluck('staff_id')->unique()->count(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->toArray();

        $workloadVisits = $this->getWorkloadVisits($facilityId, $dateFrom, $dateTo);

        $visitsPerStaff = $workloadVisits
            ->groupBy('assigned_staff_id')
            ->map(fn (Collection $rows) => $rows->pluck('id')->unique()->count());

        $highWorkloadStaff = $staffIdsAtDateTo
            ->map(function (int $staffId) use ($staffDirectory, $visitsPerStaff) {
                $staff = $staffDirectory->get($staffId, [
                    'staff_id' => $staffId,
                    'staff_uuid' => null,
                    'first_name' => null,
                    'last_name' => null,
                    'display_name' => null,
                    'full_name' => 'Unknown Staff',
                    'max_concurrent_patients' => 0,
                    'total_patients_treated' => 0,
                    'patient_satisfaction' => null,
                ]);

                $currentLoad = (int) ($visitsPerStaff->get($staffId, 0));
                $maxConcurrentPatients = (int) ($staff['max_concurrent_patients'] ?? 0);

                $workloadPercentage = $maxConcurrentPatients > 0
                    ? round(($currentLoad / $maxConcurrentPatients) * 100, 2)
                    : 0.0;

                return [
                    'staff_uuid' => $staff['staff_uuid'],
                    'first_name' => $staff['first_name'],
                    'last_name' => $staff['last_name'],
                    'display_name' => $staff['display_name'],
                    'full_name' => $staff['full_name'],
                    'max_concurrent_patients' => $maxConcurrentPatients,
                    'current_patient_load' => $currentLoad,
                    'workload_percentage' => $workloadPercentage,
                    'total_patients_treated' => (int) ($staff['total_patients_treated'] ?? 0),
                    'patient_satisfaction' => $staff['patient_satisfaction'],
                ];
            })
            ->sortByDesc('workload_percentage')
            ->take($top)
            ->values()
            ->toArray();

        $buckets = $this->buildTrendBuckets($dateFrom, $dateTo, $groupBy);

        $presenceTrend = collect($buckets)
            ->map(function (array $bucket) use ($assignments, $presenceByStaff) {
                $staffIdsAtBucketEnd = $this->getStaffIdsActiveAtPoint($assignments, $bucket['end']);

                $counts = [
                    'date' => $bucket['key'],
                    'on_duty' => 0,
                    'busy' => 0,
                    'off_duty' => 0,
                ];

                foreach ($staffIdsAtBucketEnd as $staffId) {
                    $status = $this->resolvePresenceStatusAt(
                        $presenceByStaff->get($staffId, collect()),
                        $bucket['end']
                    );

                    $counts[$status]++;
                }

                return $counts;
            })
            ->values()
            ->toArray();

        $workloadTrend = collect($buckets)
            ->map(function (array $bucket) use ($workloadVisits) {
                $rows = $workloadVisits->filter(function (array $row) use ($bucket) {
                    return $row['event_at']->betweenIncluded($bucket['start'], $bucket['end']);
                });

                $totalActivePatients = $rows->pluck('id')->unique()->count();
                $uniqueStaffAssigned = $rows->pluck('assigned_staff_id')->filter()->unique()->count();

                return [
                    'date' => $bucket['key'],
                    'total_active_patients' => $totalActivePatients,
                    'unique_staff_assigned' => $uniqueStaffAssigned,
                    'avg_patients_per_staff' => $uniqueStaffAssigned > 0
                        ? round($totalActivePatients / $uniqueStaffAssigned, 2)
                        : 0.0,
                ];
            })
            ->values()
            ->toArray();

        return [
            'current_snapshot' => $currentSnapshot,
            'role_distribution' => $roleDistribution,
            'high_workload_staff' => $highWorkloadStaff,
            'presence_trend' => $presenceTrend,
            'workload_trend' => $workloadTrend,
        ];
    }

    protected function buildCapacityMetrics(int $facilityId): array
    {
        $departments = DB::table('departments')
            ->where('facility_id', $facilityId)
            ->where('status', 'active')
            ->select([
                'id',
                'department_name',
                'department_type',
                'bed_count',
                'treatment_room_count',
                'max_concurrent_capacity',
                'average_wait_time_minutes',
            ])
            ->get();

        $staffPerDepartment = DB::table('facility_staff_roles')
            ->where('facility_id', $facilityId)
            ->where('assignment_status', 'active')
            ->get()
            ->flatMap(function ($role) {
                $departmentIds = json_decode($role->department_ids, true) ?? [];

                return collect($departmentIds)->map(fn ($departmentId) => [
                    'department_id' => $departmentId,
                    'staff_id' => $role->staff_id,
                ]);
            })
            ->groupBy('department_id')
            ->map(fn (Collection $rows) => $rows->pluck('staff_id')->unique()->count());

        $departmentRows = $departments
            ->map(function ($department) use ($staffPerDepartment) {
                $assignedStaffCount = (int) ($staffPerDepartment->get($department->id, 0));
                $maxCapacity = (int) ($department->max_concurrent_capacity ?? 0);

                return [
                    'department_name' => $department->department_name,
                    'department_type' => $department->department_type,
                    'bed_count' => (int) ($department->bed_count ?? 0),
                    'treatment_rooms' => (int) ($department->treatment_room_count ?? 0),
                    'max_capacity' => $maxCapacity,
                    'assigned_staff_count' => $assignedStaffCount,
                    'avg_wait_time_minutes' => $department->average_wait_time_minutes,
                    'capacity_utilization_hint' => $maxCapacity > 0
                        ? round(($assignedStaffCount / $maxCapacity) * 100, 2) . '% (staff vs capacity)'
                        : 'N/A',
                ];
            })
            ->values()
            ->toArray();

        $spaceTypes = DB::table('facility_spaces')
            ->where('facility_id', $facilityId)
            ->where('is_active', 1)
            ->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->get()
            ->map(fn ($row) => [
                'space_type' => $row->type,
                'total' => (int) $row->total,
            ])
            ->values()
            ->toArray();

        $occupiedSpaces = DB::table('staff_space_assignments')
            ->where('facility_id', $facilityId)
            ->whereNull('released_at')
            ->distinct('space_id')
            ->count('space_id');

        $totalActiveSpaces = DB::table('facility_spaces')
            ->where('facility_id', $facilityId)
            ->where('is_active', 1)
            ->count();

        $spaceUtilizationRate = $totalActiveSpaces > 0
            ? round(($occupiedSpaces / $totalActiveSpaces) * 100, 2)
            : 0.0;

        $wards = DB::table('wards')
            ->where('facility_id', $facilityId)
            ->where('status', 'active')
            ->select([
                'id',
                'code',
                'name',
                'ward_type',
                'building',
                'floor',
                'capacity_declared',
                'capacity_operational',
                'sex_restriction',
                'age_group',
            ])
            ->get();

        $wardsByType = $wards
            ->groupBy('ward_type')
            ->map(function (Collection $rows, string $wardType) {
                return [
                    'ward_type' => $wardType,
                    'count' => $rows->count(),
                    'total_declared_capacity' => (int) $rows->sum('capacity_declared'),
                    'total_operational_capacity' => (int) $rows->sum('capacity_operational'),
                ];
            })
            ->values()
            ->toArray();

        $wardRows = $wards
            ->map(fn ($ward) => [
                'id' => $ward->id,
                'code' => $ward->code,
                'name' => $ward->name,
                'ward_type' => $ward->ward_type,
                'building' => $ward->building,
                'floor' => $ward->floor,
                'capacity_declared' => (int) ($ward->capacity_declared ?? 0),
                'capacity_operational' => (int) ($ward->capacity_operational ?? 0),
                'sex_restriction' => $ward->sex_restriction,
                'age_group' => $ward->age_group,
                'status' => 'active',
                'estimated_occupied_beds' => null,
                'utilization_percentage' => null,
            ])
            ->values()
            ->toArray();

        return [
            'departments' => $departmentRows,
            'summary' => [
                'total_beds' => (int) $departments->sum('bed_count'),
                'total_treatment_rooms' => (int) $departments->sum('treatment_room_count'),
                'total_concurrent_capacity' => (int) $departments->sum('max_concurrent_capacity'),
                'space_utilization_rate' => $spaceUtilizationRate,
                'occupied_spaces' => (int) $occupiedSpaces,
                'total_active_spaces' => (int) $totalActiveSpaces,
                'wards' => [
                    'total_wards' => $wards->count(),
                    'total_declared_capacity' => (int) $wards->sum('capacity_declared'),
                    'total_operational_capacity' => (int) $wards->sum('capacity_operational'),
                    'wards_by_type' => $wardsByType,
                ],
            ],
            'space_types' => $spaceTypes,
            'wards' => $wardRows,
        ];
    }

    protected function buildInventoryMetrics(int $facilityId): array
    {
        $items = DB::table('inventory_items')
            ->where('facility_id', $facilityId)
            ->where('status', 'active')
            ->select([
                'item_code',
                'item_name',
                'item_category',
                'unit_of_measure',
                'package_quantity',
                'reorder_point',
                'reorder_quantity',
                'safety_stock_level',
                'max_stock_level',
                'unit_cost',
                'requires_prescription',
                'controlled_substance_schedule',
            ])
            ->get();

        $itemsNeedingReorder = $items
            ->filter(fn ($item) => !is_null($item->reorder_point) && (float) $item->package_quantity <= (float) $item->reorder_point)
            ->map(function ($item) {
                $shortageUnits = max(0, (float) $item->reorder_point - (float) $item->package_quantity);

                return [
                    'item_code' => $item->item_code,
                    'item_name' => $item->item_name,
                    'category' => $item->item_category,
                    'current_stock' => (int) ($item->package_quantity ?? 0),
                    'reorder_point' => (int) ($item->reorder_point ?? 0),
                    'shortage_units' => (int) $shortageUnits,
                    'reorder_qty' => (int) ($item->reorder_quantity ?? 0),
                    'safety_stock' => (int) ($item->safety_stock_level ?? 0),
                    'unit_cost' => (float) ($item->unit_cost ?? 0),
                    'risk_level' => $this->assessInventoryRisk($item),
                ];
            })
            ->values();

        $controlledItems = $items
            ->filter(fn ($item) => in_array($item->controlled_substance_schedule, ['II', 'III', 'IV'], true))
            ->values();

        return [
            'items_needing_reorder' => $itemsNeedingReorder->toArray(),
            'controlled_substances_count' => $controlledItems->count(),
            'controlled_items' => $controlledItems
                ->map(fn ($item) => [
                    'item_name' => $item->item_name,
                    'schedule' => $item->controlled_substance_schedule,
                ])
                ->values()
                ->toArray(),
            'summary' => [
                'total_active_items' => $items->count(),
                'items_below_reorder_point' => $itemsNeedingReorder->count(),
                'high_risk_inventory_count' => $controlledItems->count(),
            ],
        ];
    }

    protected function buildServiceMetrics(int $facilityId, int $top): array
    {
        $services = DB::table('service_catalogs')
            ->where('facility_id', $facilityId)
            ->where('status', 'active')
            ->select([
                'service_code',
                'service_name',
                'service_category',
                'price_amount',
                'currency_code',
                'risk_level',
                'default_duration_minutes',
            ])
            ->get();

        $totalRevenuePotential = round((float) $services->sum('price_amount'), 2);

        $topByPrice = $services
            ->sortByDesc('price_amount')
            ->take($top)
            ->map(fn ($service) => [
                'service_name' => $service->service_name,
                'category' => $service->service_category,
                'price' => round((float) ($service->price_amount ?? 0), 2),
                'currency' => $service->currency_code,
                'risk_level' => $service->risk_level,
                'duration_minutes' => $service->default_duration_minutes,
            ])
            ->values()
            ->toArray();

        $categoryBreakdown = $services
            ->groupBy('service_category')
            ->map(function (Collection $rows, string $category) use ($totalRevenuePotential) {
                $totalPrice = round((float) $rows->sum('price_amount'), 2);

                return [
                    'category' => $category,
                    'count' => $rows->count(),
                    'total_price_sum' => $totalPrice,
                    'avg_price' => round((float) ($rows->avg('price_amount') ?? 0), 2),
                    'share_percentage' => $totalRevenuePotential > 0
                        ? round(($totalPrice / $totalRevenuePotential) * 100, 2)
                        : 0.0,
                ];
            })
            ->sortByDesc('total_price_sum')
            ->values()
            ->toArray();

        return [
            'top_services_by_price' => $topByPrice,
            'category_breakdown' => $categoryBreakdown,
            'summary' => [
                'total_active_services' => $services->count(),
                'total_revenue_potential' => $totalRevenuePotential,
                'average_service_price' => round((float) ($services->avg('price_amount') ?? 0), 2),
                'highest_price_service' => round((float) ($services->max('price_amount') ?? 0), 2),
            ],
        ];
    }

    protected function buildFinancialMetrics(int $facilityId, array $normalized): ?array
    {
        $billingDashboard = $this->billingService->getDashboard($facilityId, [
            'date_from' => $normalized['date_from']->toDateString(),
            'date_to' => $normalized['date_to']->toDateString(),
            'group_by' => $normalized['group_by'],
            'top' => $normalized['top'],
        ]);

        return $billingDashboard['data'] ?? null;
    }

    /**
     * ---------------------------------------------------------------------
     * STAFF HELPERS
     * ---------------------------------------------------------------------
     */
    protected function getFacilityStaffAssignments(
        int $facilityId,
        CarbonInterface $dateFrom,
        CarbonInterface $dateTo
    ): Collection {
        return DB::table('facility_staff_roles')
            ->where('facility_id', $facilityId)
            ->where('assignment_status', 'active')
            ->where(function ($query) use ($dateTo) {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $dateTo->toDateString());
            })
            ->where(function ($query) use ($dateFrom) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $dateFrom->toDateString());
            })
            ->select([
                'staff_id',
                'role_code',
                'effective_from',
                'effective_to',
            ])
            ->get()
            ->map(function ($row) {
                return [
                    'staff_id' => (int) $row->staff_id,
                    'role_code' => $row->role_code ?: 'unknown',
                    'effective_from' => !empty($row->effective_from)
                        ? Carbon::parse($row->effective_from)->startOfDay()
                        : null,
                    'effective_to' => !empty($row->effective_to)
                        ? Carbon::parse($row->effective_to)->endOfDay()
                        : null,
                ];
            });
    }

    protected function getStaffIdsActiveAtPoint(Collection $assignments, CarbonInterface $point): Collection
    {
        return $assignments
            ->filter(function (array $assignment) use ($point) {
                $startsOk = empty($assignment['effective_from']) || $assignment['effective_from']->lte($point);
                $endsOk = empty($assignment['effective_to']) || $assignment['effective_to']->gte($point);

                return $startsOk && $endsOk;
            })
            ->pluck('staff_id')
            ->unique()
            ->values();
    }

    protected function getStaffDirectory(array $staffIds): Collection
    {
        if (empty($staffIds)) {
            return collect();
        }

        return DB::table('staff')
            ->leftJoin('users', 'users.id', '=', 'staff.user_id')
            ->whereIn('staff.id', $staffIds)
            ->select([
                'staff.id',
                'staff.staff_uuid',
                'staff.max_concurrent_patients',
                'staff.total_patients_treated',
                'staff.patient_satisfaction_score',
                'users.display_name',
                'users.first_name',
                'users.last_name',
            ])
            ->get()
            ->mapWithKeys(function ($row) {
                $fullName = trim((string) (
                    $row->display_name
                    ?: trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''))
                ));

                return [
                    (int) $row->id => [
                        'staff_id' => (int) $row->id,
                        'staff_uuid' => $row->staff_uuid,
                        'first_name' => $row->first_name,
                        'last_name' => $row->last_name,
                        'display_name' => $row->display_name,
                        'full_name' => $fullName !== '' ? $fullName : 'Unknown Staff',
                        'max_concurrent_patients' => (int) ($row->max_concurrent_patients ?? 0),
                        'total_patients_treated' => (int) ($row->total_patients_treated ?? 0),
                        'patient_satisfaction' => $row->patient_satisfaction_score !== null
                            ? (float) $row->patient_satisfaction_score
                            : null,
                    ],
                ];
            });
    }

    protected function getStaffPresenceEvents(
        int $facilityId,
        array $staffIds,
        CarbonInterface $dateTo
    ): Collection {
        if (empty($staffIds)) {
            return collect();
        }

        return DB::table('staff_presences')
            ->where('facility_id', $facilityId)
            ->whereIn('staff_id', $staffIds)
            ->where(function ($query) {
                $query->whereNotNull('updated_at')
                    ->orWhereNotNull('created_at');
            })
            ->whereRaw('COALESCE(updated_at, created_at) <= ?', [$dateTo->toDateTimeString()])
            ->select([
                'staff_id',
                'status',
                DB::raw('COALESCE(updated_at, created_at) as event_at'),
            ])
            ->orderBy('staff_id')
            ->orderBy('event_at')
            ->get()
            ->map(function ($row) {
                return [
                    'staff_id' => (int) $row->staff_id,
                    'status' => $this->normalizePresenceStatus($row->status),
                    'event_at' => Carbon::parse($row->event_at),
                ];
            });
    }

    protected function resolvePresenceStatusAt(Collection $events, CarbonInterface $point): string
    {
        $event = $events
            ->filter(fn (array $row) => $row['event_at']->lte($point))
            ->sortByDesc('event_at')
            ->first();

        if (!$event) {
            return 'off_duty';
        }

        return $this->normalizePresenceStatus($event['status'] ?? null);
    }

    protected function getWorkloadVisits(
        int $facilityId,
        CarbonInterface $dateFrom,
        CarbonInterface $dateTo
    ): Collection {
        return DB::table('visits')
            ->where('facility_id', $facilityId)
            ->whereIn('status', ['active', 'in_progress'])
            ->whereNotNull('assigned_staff_id')
            ->whereRaw('COALESCE(updated_at, created_at) BETWEEN ? AND ?', [
                $dateFrom->toDateTimeString(),
                $dateTo->toDateTimeString(),
            ])
            ->select([
                'id',
                'assigned_staff_id',
                DB::raw('COALESCE(updated_at, created_at) as event_at'),
            ])
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'assigned_staff_id' => (int) $row->assigned_staff_id,
                'event_at' => Carbon::parse($row->event_at),
            ]);
    }

    /**
     * ---------------------------------------------------------------------
     * GENERIC HELPERS
     * ---------------------------------------------------------------------
     *
     * Default behavior:
     * - group_by defaults to "day"
     * - date_to defaults to today endOfDay
     * - day   => today only
     * - week  => 7 days inclusive of today
     * - month => 30 days inclusive of today
     */
    protected function normalizeFilters(array $filters): array
    {
        $groupBy = in_array($filters['group_by'] ?? 'day', ['day', 'week', 'month'], true)
            ? ($filters['group_by'] ?? 'day')
            : 'day';

        $referenceDate = !empty($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : Carbon::today()->endOfDay();

        if (!empty($filters['date_from'])) {
            $dateFrom = Carbon::parse($filters['date_from'])->startOfDay();
        } else {
            $dateFrom = $this->resolveDefaultDateFrom($referenceDate, $groupBy);
        }

        $dateTo = $referenceDate->copy();

        if ($dateTo->lt($dateFrom)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'group_by' => $groupBy,
            'top' => max(1, min((int) ($filters['top'] ?? 10), 25)),
        ];
    }

    protected function resolveDefaultDateFrom(CarbonInterface $referenceDate, string $groupBy): Carbon
    {
        $reference = Carbon::parse($referenceDate);

        return match ($groupBy) {
            'week' => $reference->copy()->subDays(6)->startOfDay(),   // 7 days incl. today
            'month' => $reference->copy()->subDays(29)->startOfDay(), // 30 days incl. today
            default => $reference->copy()->startOfDay(),              // today
        };
    }

    protected function normalizePresenceStatus(?string $status): string
    {
        return match ((string) $status) {
            'on_duty' => 'on_duty',
            'busy' => 'busy',
            'off_duty', 'on_break', 'unavailable' => 'off_duty',
            default => 'off_duty',
        };
    }

    /**
     * Rolling buckets anchored to date_to:
     * - day   => 1 day per bucket
     * - week  => 7 days per bucket
     * - month => 30 days per bucket
     *
     * This ensures "week" always includes today/date_to and spans 7 days.
     */
    protected function buildTrendBuckets(
        CarbonInterface $dateFrom,
        CarbonInterface $dateTo,
        string $groupBy
    ): array {
        $windowSize = match ($groupBy) {
            'week' => 7,
            'month' => 30,
            default => 1,
        };

        $buckets = [];
        $cursorEnd = Carbon::parse($dateTo)->copy()->endOfDay();

        while ($cursorEnd->gte($dateFrom)) {
            $bucketStart = $cursorEnd->copy()->subDays($windowSize - 1)->startOfDay();

            if ($bucketStart->lt($dateFrom)) {
                $bucketStart = Carbon::parse($dateFrom)->copy()->startOfDay();
            }

            $buckets[] = [
                'key' => $bucketStart->toDateString(),
                'start' => $bucketStart,
                'end' => $cursorEnd->copy(),
            ];

            $cursorEnd = $bucketStart->copy()->subDay()->endOfDay();
        }

        return array_reverse($buckets);
    }

    protected function emptyPresenceTrend(
        CarbonInterface $dateFrom,
        CarbonInterface $dateTo,
        string $groupBy
    ): array {
        return collect($this->buildTrendBuckets($dateFrom, $dateTo, $groupBy))
            ->map(fn (array $bucket) => [
                'date' => $bucket['key'],
                'on_duty' => 0,
                'busy' => 0,
                'off_duty' => 0,
            ])
            ->values()
            ->toArray();
    }

    protected function emptyWorkloadTrend(
        CarbonInterface $dateFrom,
        CarbonInterface $dateTo,
        string $groupBy
    ): array {
        return collect($this->buildTrendBuckets($dateFrom, $dateTo, $groupBy))
            ->map(fn (array $bucket) => [
                'date' => $bucket['key'],
                'total_active_patients' => 0,
                'unique_staff_assigned' => 0,
                'avg_patients_per_staff' => 0.0,
            ])
            ->values()
            ->toArray();
    }

    protected function assessInventoryRisk(object $item): string
    {
        if (
            !empty($item->controlled_substance_schedule)
            && in_array($item->controlled_substance_schedule, ['II', 'III'], true)
        ) {
            return 'high (controlled substance)';
        }

        if ((float) ($item->package_quantity ?? 0) <= (float) ($item->safety_stock_level ?? 0)) {
            return 'high (below safety stock)';
        }

        if (
            !is_null($item->reorder_point)
            && (float) ($item->package_quantity ?? 0) <= (float) $item->reorder_point
        ) {
            return 'medium (below reorder point)';
        }

        return 'low';
    }
}
