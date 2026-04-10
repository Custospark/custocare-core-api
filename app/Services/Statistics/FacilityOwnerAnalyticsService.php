<?php

namespace App\Services\Statistics;

use App\Services\Billing\Analytics\BillingRevenueDashboardService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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

    /**
     * Main entry point: returns all decision‑oriented metrics.
     */
    public function getDashboard(int $facilityId, array $filters = []): array
    {
        try {
            $normalized = $this->normalizeFilters($filters);

            $staffMetrics = $this->getStaffMetrics($facilityId, $normalized);
            $capacityMetrics = $this->getCapacityMetrics($facilityId);
            $inventoryMetrics = $this->getInventoryMetrics($facilityId);
            $serviceMetrics = $this->getServiceMetrics($facilityId, $normalized['top']);
            $billingDashboard = $this->billingService->getDashboard($facilityId, $filters);

            return [
                'success' => true,
                'message' => 'Operational decisions dashboard generated successfully.',
                'data'    => [
                    'staff_availability'   => $staffMetrics,
                    'capacity_utilization' => $capacityMetrics,
                    'inventory_risk'       => $inventoryMetrics,
                    'service_pricing'      => $serviceMetrics,
                    'financial'            => $billingDashboard['data'] ?? null,
                ],
                'filters' => $normalized,
            ];
        } catch (Throwable $e) {
            Log::error('Operational decisions dashboard generation failed.', [
                'facility_id'   => $facilityId,
                'error_message' => $e->getMessage(),
                'file'          => $e->getFile(),
                'line'          => $e->getLine(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to generate operational dashboard.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /* ----------------------------------------------------------------------
       1. STAFF AVAILABILITY & WORKLOAD
       - Presence metrics now use updated_at + current status
       - Does NOT rely on created_at
       - Maintains existing response structure
    ---------------------------------------------------------------------- */
    protected function getStaffMetrics(int $facilityId, array $normalized): array
    {
        $dateFrom = $normalized['date_from'];
        $dateTo   = $normalized['date_to'];
        $groupBy  = $normalized['group_by'];
        $top      = $normalized['top'];

        /*
         |------------------------------------------------------------------
         | Staff presence rows in selected date range
         |
         | Since staff presence is created once and status is updated later,
         | the meaningful activity timestamp is updated_at, not created_at.
         |
         | We keep the existing output structure:
         | - on_duty
         | - busy
         | - off_duty
         |
         | Therefore:
         | - on_break      -> off_duty bucket
         | - unavailable   -> off_duty bucket
         |------------------------------------------------------------------
         */
        $presenceRows = DB::table('staff_presences')
            ->where('facility_id', $facilityId)
            ->whereNotNull('updated_at')
            ->whereBetween('updated_at', [$dateFrom, $dateTo])
            ->select('staff_id', 'status', 'updated_at')
            ->get();

        // Defensive uniqueness in case of data anomalies.
        $latestPresenceRows = $presenceRows
            ->sortByDesc('updated_at')
            ->unique('staff_id')
            ->values();

        $snapshotCounts = [
            'on_duty'  => 0,
            'busy'     => 0,
            'off_duty' => 0,
        ];

        foreach ($latestPresenceRows as $row) {
            $normalizedStatus = $this->normalizePresenceStatus($row->status);
            $snapshotCounts[$normalizedStatus]++;
        }

        $onDuty = $snapshotCounts['on_duty'];
        $busy = $snapshotCounts['busy'];
        $offDuty = $snapshotCounts['off_duty'];
        $totalActiveStaff = $onDuty + $busy;

        /*
         |------------------------------------------------------------------
         | Role distribution
         |------------------------------------------------------------------
         */
        $roleDistribution = DB::table('facility_staff_roles')
            ->where('facility_id', $facilityId)
            ->where('assignment_status', 'active')
            ->where(function ($query) use ($dateFrom) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $dateFrom->toDateString());
            })
            ->select('role_code', DB::raw('COUNT(*) as count'))
            ->groupBy('role_code')
            ->get()
            ->map(fn ($row) => [
                'role'  => $row->role_code,
                'count' => (int) $row->count,
            ])
            ->values()
            ->toArray();

        /*
         |------------------------------------------------------------------
         | Real workload in selected date range
         |------------------------------------------------------------------
         */
        $activeVisitsPerStaff = DB::table('visits')
            ->where('facility_id', $facilityId)
            ->whereIn('status', ['active', 'in_progress'])
            ->whereNotNull('assigned_staff_id')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('assigned_staff_id', DB::raw('COUNT(*) as patient_count'))
            ->groupBy('assigned_staff_id')
            ->get()
            ->keyBy('assigned_staff_id');

        /*
         |------------------------------------------------------------------
         | Staff list
         |------------------------------------------------------------------
         */
        $staffList = DB::table('staff')
            ->join('facility_staff_roles', 'staff.id', '=', 'facility_staff_roles.staff_id')
            ->join('users', 'staff.user_id', '=', 'users.id')
            ->where('facility_staff_roles.facility_id', $facilityId)
            ->where('facility_staff_roles.assignment_status', 'active')
            ->where(function ($query) use ($dateFrom) {
                $query->whereNull('facility_staff_roles.effective_to')
                    ->orWhereDate('facility_staff_roles.effective_to', '>=', $dateFrom->toDateString());
            })
            ->select(
                'staff.id',
                'staff.staff_uuid',
                'staff.max_concurrent_patients',
                'staff.total_patients_treated',
                'staff.patient_satisfaction_score',
                'users.first_name',
                'users.last_name',
                'users.display_name'
            )
            ->groupBy(
                'staff.id',
                'staff.staff_uuid',
                'staff.max_concurrent_patients',
                'staff.total_patients_treated',
                'staff.patient_satisfaction_score',
                'users.first_name',
                'users.last_name',
                'users.display_name'
            )
            ->get();

        $highWorkload = $staffList
            ->map(function ($staff) use ($activeVisitsPerStaff) {
                $currentLoad = (int) ($activeVisitsPerStaff[$staff->id]->patient_count ?? 0);
                $maxCap = (int) ($staff->max_concurrent_patients ?? 0);
                $loadPercent = $maxCap > 0 ? round(($currentLoad / $maxCap) * 100, 2) : 0;

                $fullName = $staff->display_name;
                if (empty($fullName) && ($staff->first_name || $staff->last_name)) {
                    $fullName = trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? ''));
                }
                if (empty($fullName)) {
                    $fullName = 'Unknown Staff';
                }

                return [
                    'staff_uuid'              => $staff->staff_uuid,
                    'first_name'              => $staff->first_name,
                    'last_name'               => $staff->last_name,
                    'display_name'            => $staff->display_name,
                    'full_name'               => $fullName,
                    'max_concurrent_patients' => $maxCap,
                    'current_patient_load'    => $currentLoad,
                    'workload_percentage'     => $loadPercent,
                    'total_patients_treated'  => (int) ($staff->total_patients_treated ?? 0),
                    'patient_satisfaction'    => $staff->patient_satisfaction_score !== null
                        ? (float) $staff->patient_satisfaction_score
                        : null,
                ];
            })
            ->sortByDesc('workload_percentage')
            ->values()
            ->take($top)
            ->toArray();

        /*
         |------------------------------------------------------------------
         | Presence trend
         |
         | Uses updated_at only for staff presence timing.
         | No created_at reliance.
         |------------------------------------------------------------------
         */
        $presenceTrendBuckets = $this->initializeTrendBuckets(
            $dateFrom,
            $dateTo,
            $groupBy,
            fn (string $date) => [
                'date'     => $date,
                'on_duty'  => 0,
                'busy'     => 0,
                'off_duty' => 0,
            ]
        );

        foreach ($presenceRows as $row) {
            if (empty($row->updated_at)) {
                continue;
            }

            $bucketKey = $this->formatTrendBucket(Carbon::parse($row->updated_at), $groupBy);

            if (!isset($presenceTrendBuckets[$bucketKey])) {
                $presenceTrendBuckets[$bucketKey] = [
                    'date'     => $bucketKey,
                    'on_duty'  => 0,
                    'busy'     => 0,
                    'off_duty' => 0,
                ];
            }

            $normalizedStatus = $this->normalizePresenceStatus($row->status);
            $presenceTrendBuckets[$bucketKey][$normalizedStatus]++;
        }

        $presenceTrend = collect($presenceTrendBuckets)
            ->sortBy('date')
            ->values()
            ->toArray();

        /*
         |------------------------------------------------------------------
         | Workload trend
         |------------------------------------------------------------------
         */
        $visitRows = DB::table('visits')
            ->where('facility_id', $facilityId)
            ->whereIn('status', ['active', 'in_progress'])
            ->whereNotNull('assigned_staff_id')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('assigned_staff_id', 'created_at')
            ->get();

        $workloadTrendBuckets = $this->initializeTrendBuckets(
            $dateFrom,
            $dateTo,
            $groupBy,
            fn (string $date) => [
                'date'                   => $date,
                'total_active_patients'  => 0,
                'unique_staff_assigned'  => 0,
                'avg_patients_per_staff' => 0,
                '_staff_ids'             => [],
            ]
        );

        foreach ($visitRows as $visit) {
            if (empty($visit->created_at)) {
                continue;
            }

            $bucketKey = $this->formatTrendBucket(Carbon::parse($visit->created_at), $groupBy);

            if (!isset($workloadTrendBuckets[$bucketKey])) {
                $workloadTrendBuckets[$bucketKey] = [
                    'date'                   => $bucketKey,
                    'total_active_patients'  => 0,
                    'unique_staff_assigned'  => 0,
                    'avg_patients_per_staff' => 0,
                    '_staff_ids'             => [],
                ];
            }

            $workloadTrendBuckets[$bucketKey]['total_active_patients']++;
            $workloadTrendBuckets[$bucketKey]['_staff_ids'][$visit->assigned_staff_id] = true;
        }

        $workloadTrend = collect($workloadTrendBuckets)
            ->sortBy('date')
            ->map(function ($bucket) {
                $uniqueStaff = count($bucket['_staff_ids']);
                $totalPatients = (int) $bucket['total_active_patients'];

                unset($bucket['_staff_ids']);

                $bucket['unique_staff_assigned'] = $uniqueStaff;
                $bucket['avg_patients_per_staff'] = $uniqueStaff > 0
                    ? round($totalPatients / $uniqueStaff, 2)
                    : 0;

                return $bucket;
            })
            ->values()
            ->toArray();

        return [
            'current_snapshot' => [
                'staff_on_duty'  => $onDuty,
                'staff_busy'     => $busy,
                'staff_off_duty' => $offDuty,
                'total_active'   => $totalActiveStaff,
                'occupancy_rate' => $totalActiveStaff > 0
                    ? round(($busy / $totalActiveStaff) * 100, 2)
                    : 0,
            ],
            'role_distribution'   => $roleDistribution,
            'high_workload_staff' => $highWorkload,
            'presence_trend'      => $presenceTrend,
            'workload_trend'      => $workloadTrend,
        ];
    }

    /* ----------------------------------------------------------------------
       2. DEPARTMENT & SPACE CAPACITY
    ---------------------------------------------------------------------- */
    protected function getCapacityMetrics(int $facilityId): array
    {
        $departments = DB::table('departments')
            ->where('facility_id', $facilityId)
            ->where('status', 'active')
            ->select(
                'id',
                'department_name',
                'department_type',
                'bed_count',
                'treatment_room_count',
                'max_concurrent_capacity',
                'average_wait_time_minutes'
            )
            ->get();

        $totalBeds = $departments->sum('bed_count');
        $totalRooms = $departments->sum('treatment_room_count');
        $totalCapacity = $departments->sum('max_concurrent_capacity');

        $staffPerDept = DB::table('facility_staff_roles')
            ->where('facility_id', $facilityId)
            ->where('assignment_status', 'active')
            ->get()
            ->flatMap(function ($role) {
                $deptIds = json_decode($role->department_ids, true) ?? [];

                return collect($deptIds)->map(fn ($id) => [
                    'dept_id'  => $id,
                    'staff_id' => $role->staff_id,
                ]);
            })
            ->groupBy('dept_id')
            ->map->count();

        $departmentsWithStaff = $departments->map(function ($dept) use ($staffPerDept) {
            $assignedStaff = $staffPerDept[$dept->id] ?? 0;

            return [
                'department_name'           => $dept->department_name,
                'department_type'           => $dept->department_type,
                'bed_count'                 => (int) $dept->bed_count,
                'treatment_rooms'           => (int) $dept->treatment_room_count,
                'max_capacity'              => (int) $dept->max_concurrent_capacity,
                'assigned_staff_count'      => $assignedStaff,
                'avg_wait_time_minutes'     => $dept->average_wait_time_minutes,
                'capacity_utilization_hint' => $dept->max_concurrent_capacity > 0
                    ? round(($assignedStaff / $dept->max_concurrent_capacity) * 100, 2) . '% (staff vs capacity)'
                    : 'N/A',
            ];
        });

        $spaces = DB::table('facility_spaces')
            ->where('facility_id', $facilityId)
            ->where('is_active', 1)
            ->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->get()
            ->map(fn ($row) => [
                'space_type' => $row->type,
                'total'      => (int) $row->total,
            ])
            ->values();

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
            : 0;

        $wards = DB::table('wards')
            ->where('facility_id', $facilityId)
            ->where('status', 'active')
            ->select(
                'id',
                'code',
                'name',
                'ward_type',
                'building',
                'floor',
                'capacity_declared',
                'capacity_operational',
                'sex_restriction',
                'age_group'
            )
            ->get();

        $totalDeclaredCapacity = $wards->sum('capacity_declared');
        $totalOperationalCapacity = $wards->sum('capacity_operational');

        $wardsByType = $wards
            ->groupBy('ward_type')
            ->map(fn ($group, $type) => [
                'ward_type'                => $type,
                'count'                    => $group->count(),
                'total_declared_capacity'  => $group->sum('capacity_declared'),
                'total_operational_capacity' => $group->sum('capacity_operational'),
            ])
            ->values();

        $wardsWithDetails = $wards->map(function ($ward) {
            return [
                'id'                      => $ward->id,
                'code'                    => $ward->code,
                'name'                    => $ward->name,
                'ward_type'               => $ward->ward_type,
                'building'                => $ward->building,
                'floor'                   => $ward->floor,
                'capacity_declared'       => (int) $ward->capacity_declared,
                'capacity_operational'    => (int) $ward->capacity_operational,
                'sex_restriction'         => $ward->sex_restriction,
                'age_group'               => $ward->age_group,
                'status'                  => 'active',
                'estimated_occupied_beds' => null,
                'utilization_percentage'  => null,
            ];
        });

        return [
            'departments' => $departmentsWithStaff,
            'summary'     => [
                'total_beds'                => $totalBeds,
                'total_treatment_rooms'     => $totalRooms,
                'total_concurrent_capacity' => $totalCapacity,
                'space_utilization_rate'    => $spaceUtilizationRate,
                'occupied_spaces'           => $occupiedSpaces,
                'total_active_spaces'       => $totalActiveSpaces,
                'wards'                     => [
                    'total_wards'                => $wards->count(),
                    'total_declared_capacity'    => $totalDeclaredCapacity,
                    'total_operational_capacity' => $totalOperationalCapacity,
                    'wards_by_type'              => $wardsByType,
                ],
            ],
            'space_types' => $spaces,
            'wards'       => $wardsWithDetails,
        ];
    }

    /* ----------------------------------------------------------------------
       3. INVENTORY RISK
    ---------------------------------------------------------------------- */
    protected function getInventoryMetrics(int $facilityId): array
    {
        $items = DB::table('inventory_items')
            ->where('facility_id', $facilityId)
            ->where('status', 'active')
            ->select(
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
                'controlled_substance_schedule'
            )
            ->get();

        $needsReorder = $items
            ->filter(fn ($item) => !is_null($item->reorder_point) && $item->package_quantity <= $item->reorder_point)
            ->map(function ($item) {
                $shortage = $item->reorder_point - $item->package_quantity;

                return [
                    'item_code'      => $item->item_code,
                    'item_name'      => $item->item_name,
                    'category'       => $item->item_category,
                    'current_stock'  => (int) $item->package_quantity,
                    'reorder_point'  => (int) $item->reorder_point,
                    'shortage_units' => $shortage > 0 ? $shortage : 0,
                    'reorder_qty'    => (int) $item->reorder_quantity,
                    'safety_stock'   => (int) $item->safety_stock_level,
                    'unit_cost'      => (float) $item->unit_cost,
                    'risk_level'     => $this->assessInventoryRisk($item),
                ];
            })
            ->values();

        $controlled = $items
            ->filter(fn ($item) => in_array($item->controlled_substance_schedule, ['II', 'III', 'IV'], true))
            ->values();

        return [
            'items_needing_reorder'       => $needsReorder,
            'controlled_substances_count' => $controlled->count(),
            'controlled_items'            => $controlled
                ->map(fn ($item) => [
                    'item_name' => $item->item_name,
                    'schedule'  => $item->controlled_substance_schedule,
                ])
                ->values(),
            'summary'                     => [
                'total_active_items'        => $items->count(),
                'items_below_reorder_point' => $needsReorder->count(),
                'high_risk_inventory_count' => $controlled->count(),
            ],
        ];
    }

    protected function assessInventoryRisk($item): string
    {
        if ($item->controlled_substance_schedule && in_array($item->controlled_substance_schedule, ['II', 'III'], true)) {
            return 'high (controlled substance)';
        }

        if ($item->package_quantity <= ($item->safety_stock_level ?? 0)) {
            return 'high (below safety stock)';
        }

        if (!is_null($item->reorder_point) && $item->package_quantity <= $item->reorder_point) {
            return 'medium (below reorder point)';
        }

        return 'low';
    }

    /* ----------------------------------------------------------------------
       4. SERVICE CATALOG PRICING & REVENUE POTENTIAL
    ---------------------------------------------------------------------- */
    protected function getServiceMetrics(int $facilityId, int $top): array
    {
        $services = DB::table('service_catalogs')
            ->where('facility_id', $facilityId)
            ->where('status', 'active')
            ->select(
                'service_code',
                'service_name',
                'service_category',
                'price_amount',
                'currency_code',
                'risk_level',
                'default_duration_minutes'
            )
            ->get();

        $totalRevenuePotential = $services->sum('price_amount');

        $topByPrice = $services
            ->sortByDesc('price_amount')
            ->take($top)
            ->map(fn ($service) => [
                'service_name'     => $service->service_name,
                'category'         => $service->service_category,
                'price'            => (float) $service->price_amount,
                'currency'         => $service->currency_code,
                'risk_level'       => $service->risk_level,
                'duration_minutes' => $service->default_duration_minutes,
            ])
            ->values();

        $categoryBreakdown = $services
            ->groupBy('service_category')
            ->map(fn ($group, $category) => [
                'category'         => $category,
                'count'            => $group->count(),
                'total_price_sum'  => round($group->sum('price_amount'), 2),
                'avg_price'        => round($group->avg('price_amount'), 2),
                'share_percentage' => $totalRevenuePotential > 0
                    ? round(($group->sum('price_amount') / $totalRevenuePotential) * 100, 2)
                    : 0,
            ])
            ->sortByDesc('total_price_sum')
            ->values();

        return [
            'top_services_by_price' => $topByPrice,
            'category_breakdown'    => $categoryBreakdown,
            'summary'               => [
                'total_active_services'   => $services->count(),
                'total_revenue_potential' => round($totalRevenuePotential, 2),
                'average_service_price'   => round($services->avg('price_amount'), 2),
                'highest_price_service'   => $services->max('price_amount'),
            ],
        ];
    }

    /* ----------------------------------------------------------------------
       Helpers
    ---------------------------------------------------------------------- */
    protected function normalizeFilters(array $filters): array
    {
        $groupBy = in_array($filters['group_by'] ?? 'day', ['day', 'week', 'month'], true)
            ? ($filters['group_by'] ?? 'day')
            : 'day';

        $dateFrom = !empty($filters['date_from'])
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : now()->subDays(30)->startOfDay();

        $dateTo = !empty($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : now()->endOfDay();

        if ($dateTo->lt($dateFrom)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        return [
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'group_by'  => $groupBy,
            'top'       => max(1, min((int) ($filters['top'] ?? 10), 25)),
        ];
    }

    /**
     * Keep output contract unchanged.
     * Existing contract only has: on_duty, busy, off_duty
     */
    protected function normalizePresenceStatus(?string $status): string
    {
        return match ($status) {
            'on_duty' => 'on_duty',
            'busy' => 'busy',
            'off_duty', 'on_break', 'unavailable' => 'off_duty',
            default => 'off_duty',
        };
    }

    protected function formatTrendBucket(CarbonInterface $date, string $groupBy): string
    {
        return match ($groupBy) {
            'week'  => $date->copy()->startOfWeek()->toDateString(),
            'month' => $date->copy()->startOfMonth()->toDateString(),
            default => $date->copy()->toDateString(),
        };
    }

    protected function initializeTrendBuckets(
        CarbonInterface $dateFrom,
        CarbonInterface $dateTo,
        string $groupBy,
        callable $resolver
    ): array {
        $buckets = [];

        $cursor = $this->getBucketStart($dateFrom, $groupBy);
        $end = $this->getBucketStart($dateTo, $groupBy);

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $buckets[$key] = $resolver($key);
            $cursor = $this->incrementBucket($cursor, $groupBy);
        }

        return $buckets;
    }

    protected function getBucketStart(CarbonInterface $date, string $groupBy): Carbon
    {
        $date = Carbon::parse($date);

        return match ($groupBy) {
            'week'  => $date->startOfWeek(),
            'month' => $date->startOfMonth(),
            default => $date->startOfDay(),
        };
    }

    protected function incrementBucket(CarbonInterface $date, string $groupBy): Carbon
    {
        $date = Carbon::parse($date);

        return match ($groupBy) {
            'week'  => $date->addWeek(),
            'month' => $date->addMonth(),
            default => $date->addDay(),
        };
    }
}
