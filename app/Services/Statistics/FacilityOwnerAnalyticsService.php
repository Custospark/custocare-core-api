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

    /**
     * Main entry point: returns all decision‑oriented metrics.
     */
    public function getDashboard(int $facilityId, array $filters = []): array
    {
        try {
            $normalized = $this->normalizeFilters($filters);

            // 1. Staff availability & workload
            $staffMetrics = $this->getStaffMetrics($facilityId, $normalized);

            // 2. Department & space capacity
            $capacityMetrics = $this->getCapacityMetrics($facilityId);

            // 3. Inventory risk (using package_quantity as current stock)
            $inventoryMetrics = $this->getInventoryMetrics($facilityId);

            // 4. Service catalog pricing & revenue potential
            $serviceMetrics = $this->getServiceMetrics($facilityId, $normalized['top']);

            // 5. Financial health – reuse existing billing dashboard
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
                'facility_id'    => $facilityId,
                'error_message'  => $e->getMessage(),
                'file'           => $e->getFile(),
                'line'           => $e->getLine(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to generate operational dashboard.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /* ----------------------------------------------------------------------
       1. STAFF AVAILABILITY & WORKLOAD (respects date range for trends)
    ---------------------------------------------------------------------- */
        protected function getStaffMetrics(int $facilityId, array $normalized): array
    {
        // ----- Real‑time snapshot (ignores date filters) -----
        $presenceStats = DB::table('staff_presences')
            ->where('facility_id', $facilityId)
            ->whereNull('ended_at')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        $onDuty   = $presenceStats['on_duty'] ?? 0;
        $busy     = $presenceStats['busy'] ?? 0;
        $offDuty  = $presenceStats['off_duty'] ?? 0;
        $totalActiveStaff = $onDuty + $busy;

        // Role distribution (active assignments)
        $roleDistribution = DB::table('facility_staff_roles')
            ->where('facility_id', $facilityId)
            ->where('assignment_status', 'active')
            ->where(function ($q) {
                $q->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', now()->toDateString());
            })
            ->select('role_code', DB::raw('COUNT(*) as count'))
            ->groupBy('role_code')
            ->get()
            ->map(fn($row) => ['role' => $row->role_code, 'count' => $row->count])
            ->values()
            ->toArray();

        // Real‑time workload (active/in-progress visits per staff)
        $activeVisitsPerStaff = DB::table('visits')
            ->where('facility_id', $facilityId)
            ->whereIn('status', ['active', 'in_progress'])
            ->whereNotNull('assigned_staff_id')
            ->select('assigned_staff_id', DB::raw('COUNT(*) as patient_count'))
            ->groupBy('assigned_staff_id')
            ->get()
            ->keyBy('assigned_staff_id');

        // Staff list with user details (first_name, last_name, display_name)
        $staffList = DB::table('staff')
            ->join('facility_staff_roles', 'staff.id', '=', 'facility_staff_roles.staff_id')
            ->join('users', 'staff.user_id', '=', 'users.id')  // ← join users table
            ->where('facility_staff_roles.facility_id', $facilityId)
            ->where('facility_staff_roles.assignment_status', 'active')
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
            ->get();

        $highWorkload = $staffList->map(function ($staff) use ($activeVisitsPerStaff) {
            $currentLoad = (int) ($activeVisitsPerStaff[$staff->id]->patient_count ?? 0);
            $maxCap = (int) $staff->max_concurrent_patients;
            $loadPercent = $maxCap > 0 ? round(($currentLoad / $maxCap) * 100, 2) : 0;

            // Build full name (fallback to display_name if first/last missing)
            $fullName = $staff->display_name;
            if (empty($fullName) && ($staff->first_name || $staff->last_name)) {
                $fullName = trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? ''));
            }
            if (empty($fullName)) {
                $fullName = 'Unknown Staff';
            }

            return [
                'staff_uuid'               => $staff->staff_uuid,
                'first_name'               => $staff->first_name,
                'last_name'                => $staff->last_name,
                'display_name'             => $staff->display_name,
                'full_name'                => $fullName,
                'max_concurrent_patients'  => $maxCap,
                'current_patient_load'     => $currentLoad,
                'workload_percentage'      => $loadPercent,
                'total_patients_treated'   => (int) $staff->total_patients_treated,
                'patient_satisfaction'     => $staff->patient_satisfaction_score ? (float) $staff->patient_satisfaction_score : null,
            ];
        })->sortByDesc('workload_percentage')->values()->take($normalized['top'])->toArray();

        // ----- Presence trend over the selected date range -----
        $presenceTrend = DB::table('staff_presences')
            ->where('facility_id', $facilityId)
            ->whereBetween('created_at', [$normalized['date_from'], $normalized['date_to']])
            ->select(
                DB::raw("DATE(created_at) as date"),
                'status',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(fn($dayRows, $date) => [
                'date'    => $date,
                'on_duty' => (int) ($dayRows->firstWhere('status', 'on_duty')->count ?? 0),
                'busy'    => (int) ($dayRows->firstWhere('status', 'busy')->count ?? 0),
                'off_duty'=> (int) ($dayRows->firstWhere('status', 'off_duty')->count ?? 0),
            ])
            ->values()
            ->toArray();

        // ----- Workload trend (average active patients per staff per day) -----
        $workloadTrend = DB::table('visits')
            ->where('facility_id', $facilityId)
            ->whereIn('status', ['active', 'in_progress'])
            ->whereNotNull('assigned_staff_id')
            ->whereBetween('created_at', [$normalized['date_from'], $normalized['date_to']])
            ->select(
                DB::raw("DATE(created_at) as date"),
                'assigned_staff_id',
                DB::raw('COUNT(*) as patient_count')
            )
            ->groupBy('date', 'assigned_staff_id')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function ($dayVisits, $date) {
                $totalPatients = $dayVisits->sum('patient_count');
                $uniqueStaff = $dayVisits->pluck('assigned_staff_id')->unique()->count();
                $avgLoad = $uniqueStaff > 0 ? round($totalPatients / $uniqueStaff, 2) : 0;
                return [
                    'date'                      => $date,
                    'total_active_patients'     => (int) $totalPatients,
                    'unique_staff_assigned'     => $uniqueStaff,
                    'avg_patients_per_staff'    => $avgLoad,
                ];
            })
            ->values()
            ->toArray();

        return [
            'current_snapshot' => [
                'staff_on_duty'    => $onDuty,
                'staff_busy'       => $busy,
                'staff_off_duty'   => $offDuty,
                'total_active'     => $totalActiveStaff,
                'occupancy_rate'   => $totalActiveStaff > 0 ? round(($busy / $totalActiveStaff) * 100, 2) : 0,
            ],
            'role_distribution'   => $roleDistribution,
            'high_workload_staff' => $highWorkload,
            'presence_trend'      => $presenceTrend,
            'workload_trend'      => $workloadTrend,
        ];
    }

    /* ----------------------------------------------------------------------
       2. DEPARTMENT & SPACE CAPACITY (point‑in‑time, no date filter)
    ---------------------------------------------------------------------- */
        protected function getCapacityMetrics(int $facilityId): array
    {
        // ----- Departments -----
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
                return collect($deptIds)->map(fn($id) => ['dept_id' => $id, 'staff_id' => $role->staff_id]);
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

        // ----- Spaces (consultation, triage, lab, theatre, ward, pharmacy) -----
        $spaces = DB::table('facility_spaces')
            ->where('facility_id', $facilityId)
            ->where('is_active', 1)
            ->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->get()
            ->map(fn($row) => ['space_type' => $row->type, 'total' => $row->total])
            ->values();

        $occupiedSpaces = DB::table('staff_space_assignments')
            ->where('facility_id', $facilityId)
            ->whereNull('released_at')
            ->select('space_id')
            ->distinct()
            ->count();

        $totalActiveSpaces = DB::table('facility_spaces')
            ->where('facility_id', $facilityId)
            ->where('is_active', 1)
            ->count();

        $spaceUtilizationRate = $totalActiveSpaces > 0
            ? round(($occupiedSpaces / $totalActiveSpaces) * 100, 2)
            : 0;

        // ----- WARDS (new) -----
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

        // Calculate total declared and operational capacity across wards
        $totalDeclaredCapacity = $wards->sum('capacity_declared');
        $totalOperationalCapacity = $wards->sum('capacity_operational');

        // Group wards by type for summary
        $wardsByType = $wards
            ->groupBy('ward_type')
            ->map(fn($group, $type) => [
                'ward_type' => $type,
                'count' => $group->count(),
                'total_declared_capacity' => $group->sum('capacity_declared'),
                'total_operational_capacity' => $group->sum('capacity_operational'),
            ])
            ->values();

        // Optionally: compute estimated occupancy if you have bed assignments or visit data.
        // For now we mark as null; you can later join visits where visit_type='inpatient' and link to ward via department.
        $wardsWithDetails = $wards->map(function ($ward) {
            return [
                'id'                        => $ward->id,
                'code'                      => $ward->code,
                'name'                      => $ward->name,
                'ward_type'                 => $ward->ward_type,
                'building'                  => $ward->building,
                'floor'                     => $ward->floor,
                'capacity_declared'         => (int) $ward->capacity_declared,
                'capacity_operational'      => (int) $ward->capacity_operational,
                'sex_restriction'           => $ward->sex_restriction,
                'age_group'                 => $ward->age_group,
                'status'                    => 'active',
                // Placeholder for future occupancy (e.g., from bed assignments or visit counts)
                'estimated_occupied_beds'   => null,
                'utilization_percentage'    => null,
            ];
        });

        return [
            'departments' => $departmentsWithStaff,
            'summary' => [
                'total_beds'                => $totalBeds,
                'total_treatment_rooms'     => $totalRooms,
                'total_concurrent_capacity' => $totalCapacity,
                'space_utilization_rate'    => $spaceUtilizationRate,
                'occupied_spaces'           => $occupiedSpaces,
                'total_active_spaces'       => $totalActiveSpaces,
                'wards' => [
                    'total_wards'                   => $wards->count(),
                    'total_declared_capacity'       => $totalDeclaredCapacity,
                    'total_operational_capacity'    => $totalOperationalCapacity,
                    'wards_by_type'                 => $wardsByType,
                ],
            ],
            'space_types' => $spaces,
            'wards' => $wardsWithDetails,   // new section
        ];
    }

    /* ----------------------------------------------------------------------
       3. INVENTORY RISK (package_quantity as current stock)
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

        $needsReorder = $items->filter(function ($item) {
            return !is_null($item->reorder_point) && $item->package_quantity <= $item->reorder_point;
        })->map(function ($item) {
            $shortage = $item->reorder_point - $item->package_quantity;
            return [
                'item_code'        => $item->item_code,
                'item_name'        => $item->item_name,
                'category'         => $item->item_category,
                'current_stock'    => (int) $item->package_quantity,
                'reorder_point'    => (int) $item->reorder_point,
                'shortage_units'   => $shortage > 0 ? $shortage : 0,
                'reorder_qty'      => (int) $item->reorder_quantity,
                'safety_stock'     => (int) $item->safety_stock_level,
                'unit_cost'        => (float) $item->unit_cost,
                'risk_level'       => $this->assessInventoryRisk($item),
            ];
        })->values();

        $controlled = $items->filter(function ($item) {
            return in_array($item->controlled_substance_schedule, ['II', 'III', 'IV']);
        })->values();

        return [
            'items_needing_reorder' => $needsReorder,
            'controlled_substances_count' => $controlled->count(),
            'controlled_items' => $controlled->map(fn($i) => [
                'item_name' => $i->item_name,
                'schedule'  => $i->controlled_substance_schedule,
            ])->values(),
            'summary' => [
                'total_active_items'        => $items->count(),
                'items_below_reorder_point' => $needsReorder->count(),
                'high_risk_inventory_count' => $controlled->count(),
            ],
        ];
    }

    protected function assessInventoryRisk($item): string
    {
        if ($item->controlled_substance_schedule && in_array($item->controlled_substance_schedule, ['II', 'III'])) {
            return 'high (controlled substance)';
        }
        if ($item->package_quantity <= ($item->safety_stock_level ?? 0)) {
            return 'high (below safety stock)';
        }
        if ($item->package_quantity <= $item->reorder_point) {
            return 'medium (below reorder point)';
        }
        return 'low';
    }

    /* ----------------------------------------------------------------------
       4. SERVICE CATALOG PRICING & REVENUE POTENTIAL (point‑in‑time)
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
            ->map(fn($s) => [
                'service_name'     => $s->service_name,
                'category'         => $s->service_category,
                'price'            => (float) $s->price_amount,
                'currency'         => $s->currency_code,
                'risk_level'       => $s->risk_level,
                'duration_minutes' => $s->default_duration_minutes,
            ])
            ->values();

        $categoryBreakdown = $services
            ->groupBy('service_category')
            ->map(fn($catServices, $cat) => [
                'category'          => $cat,
                'count'             => $catServices->count(),
                'total_price_sum'   => round($catServices->sum('price_amount'), 2),
                'avg_price'         => round($catServices->avg('price_amount'), 2),
                'share_percentage'  => $totalRevenuePotential > 0
                    ? round(($catServices->sum('price_amount') / $totalRevenuePotential) * 100, 2)
                    : 0,
            ])
            ->sortByDesc('total_price_sum')
            ->values();

        return [
            'top_services_by_price' => $topByPrice,
            'category_breakdown'    => $categoryBreakdown,
            'summary' => [
                'total_active_services'     => $services->count(),
                'total_revenue_potential'   => round($totalRevenuePotential, 2),
                'average_service_price'     => round($services->avg('price_amount'), 2),
                'highest_price_service'     => $services->max('price_amount'),
            ],
        ];
    }

    /* ----------------------------------------------------------------------
       Helpers
    ---------------------------------------------------------------------- */
    protected function normalizeFilters(array $filters): array
    {
        $groupBy = in_array($filters['group_by'] ?? 'day', ['day', 'week', 'month']) ? ($filters['group_by'] ?? 'day') : 'day';
        $dateFrom = !empty($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : now()->subDays(30)->startOfDay();
        $dateTo   = !empty($filters['date_to'])   ? Carbon::parse($filters['date_to'])->endOfDay()   : now()->endOfDay();
        if ($dateTo->lt($dateFrom)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }
        return [
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'group_by'  => $groupBy,
            'top'       => max(1, min((int)($filters['top'] ?? 10), 25)),
        ];
    }
}