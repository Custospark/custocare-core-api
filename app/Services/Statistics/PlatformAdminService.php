<?php

namespace App\Services\Statistics;

use App\Models\Facility;
use App\Models\User;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlatformAdminService
{
    // -------------------------------------------------------------------------
    // FACILITIES
    // -------------------------------------------------------------------------

   public function getFacilitiesList(
    array $dateRange,
    ?string $status = null,
    ?string $operationalStatus = null,
    ?string $search = null,
    int $perPage = 15,
    int $page = 1
): array {
    $query = Facility::query()
        ->when($dateRange['from'] ?? null, fn($q) => $q->where('created_at', '>=', $dateRange['from']))
        ->when($dateRange['to'] ?? null,   fn($q) => $q->where('created_at', '<=', $dateRange['to']))
        ->when($status, fn($q) => $q->where('status', $status))
        ->when($operationalStatus, fn($q) => $q->where('operational_status', $operationalStatus))
        ->when($search, function ($q) use ($search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('facility_name', 'like', "%{$search}%")
                    ->orWhere('facility_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('main_phone', 'like', "%{$search}%");
            });
        })
        ->orderBy('created_at', 'desc');

    $paginator = $query->paginate($perPage, ['*'], 'page', $page);

    $facilityIds = $paginator->getCollection()->pluck('id')->all();
    $ownersMap   = $this->getFacilityOwners($facilityIds);
    $staffMap    = $this->getFacilityStaff($facilityIds);
    $billingMap  = $this->getFacilityBillingTotals($facilityIds, $dateRange);
    $transformed = $paginator->getCollection()->map(function ($facility) use ($ownersMap, $staffMap, $billingMap) {
        return [
            'id'                 => $facility->id,
            'facility_uuid'      => $facility->facility_uuid,
            'facility_code'      => $facility->facility_code,
            'facility_currency'  => $facility->currency,
            'facility_website'   => $facility->facility_website,
            'name'               => $facility->facility_name,
            'location'           => [
                'address_line1'  => $facility->address_line1,
                'address_line2'  => $facility->address_line2,
                'city'           => $facility->city,
                'state_province' => $facility->state_province,
                'postal_code'    => $facility->postal_code,
                'country_code'   => $facility->country_code,
            ],
            'phone'              => $facility->main_phone,
            'email'              => $facility->email,
            'status'             => $facility->status,
            'operational_status' => $facility->operational_status,
            'status_reason'      => $facility->status_reason,
            'status_set_at'      => $this->formatTimestamp($facility->status_set_at),
            'created_at'         => $this->formatTimestamp($facility->created_at),
            'owner'              => $ownersMap->get($facility->id),
            'staff'              => $staffMap->get($facility->id, collect())->values()->all(),
            'staff_count'        => $staffMap->get($facility->id, collect())->count(),
            'billing'            => $billingMap->get($facility->id, ['total_paid' => 0, 'balance' => 0]),
        ];
    });

    return [
        'data'         => $transformed->all(),
        'current_page' => $paginator->currentPage(),
        'per_page'     => $paginator->perPage(),
        'total'        => $paginator->total(),
        'last_page'    => $paginator->lastPage(),
    ];
}

    /**
     * Get facility counts for dashboard summary.
     * 
     * @param Carbon|null $referenceDate  Date to use for "today", "this_week", "this_month" (defaults to now)
     */
    public function getFacilityCountsByDateRanges(?Carbon $referenceDate = null): array
    {
        $ref = $referenceDate ?? now();
        
        $todayStart = $ref->copy()->startOfDay();
        $todayEnd   = $ref->copy()->endOfDay();
        
        $weekStart = $ref->copy()->startOfWeek();
        $weekEnd   = $ref->copy()->endOfWeek();
        
        $monthStart = $ref->copy()->startOfMonth();
        $monthEnd   = $ref->copy()->endOfMonth();

        return [
            'total'      => Facility::count(),
            'today'      => Facility::whereBetween('created_at', [$todayStart, $todayEnd])->count(),
            'this_week'  => Facility::whereBetween('created_at', [$weekStart, $weekEnd])->count(),
            'this_month' => Facility::whereBetween('created_at', [$monthStart, $monthEnd])->count(),
            'active'     => Facility::where('status', 'active')->count(),
            'suspended'  => Facility::where('status', 'suspended')->count(),
            'banned'     => Facility::where('status', 'banned')->count(),
        ];
    }

    public function updateFacilityStatus(int $facilityId, string $status, ?string $reason, int $adminUserId): bool
    {
        $facility = Facility::find($facilityId);
        if (!$facility) return false;

        $facility->status         = $status;
        $facility->status_reason  = $reason;
        $facility->status_set_at  = now();
        $facility->status_set_by  = $adminUserId;

        return $facility->save();
    }

    protected function getFacilityOwners(array $facilityIds): Collection
    {
        if (empty($facilityIds)) return collect();

        $owners = DB::table('facility_owners')
            ->join('staff', 'facility_owners.staff_id', '=', 'staff.id')
            ->join('users', 'staff.user_id', '=', 'users.id')
            ->whereIn('facility_owners.facility_id', $facilityIds)
            ->select('facility_owners.facility_id', 'users.first_name', 'users.last_name', 'users.display_name',
                     'users.email_encrypted', 'users.phone_encrypted')
            ->get();

        return $owners->groupBy('facility_id')->map(function ($group) {
            $owner = $group->first();
            return [
                'name'  => trim($owner->first_name . ' ' . $owner->last_name) ?? $owner->display_name ?? null,
                'email' => $this->decryptValue($owner->email_encrypted),
                'phone' => $this->decryptValue($owner->phone_encrypted),
            ];
        });
    }

    protected function getFacilityStaff(array $facilityIds): Collection
    {
        if (empty($facilityIds)) return collect();

        $staff = DB::table('facility_staff_roles')
            ->join('staff', 'facility_staff_roles.staff_id', '=', 'staff.id')
            ->join('users', 'staff.user_id', '=', 'users.id')
            ->whereIn('facility_staff_roles.facility_id', $facilityIds)
            ->whereIn('facility_staff_roles.assignment_status', ['active','on_leave','suspended'])
            ->select('facility_staff_roles.facility_id', 'facility_staff_roles.role_code',
                     'users.first_name', 'users.last_name', 'users.display_name',
                     'users.email_encrypted', 'users.phone_encrypted')
            ->get();

        return $staff->groupBy('facility_id')->map(function ($group) {
            return $group->map(function ($s) {
                return [
                    'name'  => $s->display_name ?: trim($s->first_name . ' ' . $s->last_name),
                    'email' => $this->decryptValue($s->email_encrypted),
                    'phone' => $this->decryptValue($s->phone_encrypted),
                    'role'  => $s->role_code,
                ];
            });
        });
    }

    protected function getFacilityBillingTotals(array $facilityIds, ?array $dateRange = null): Collection
{
    if (empty($facilityIds)) return collect();

    $query = DB::table('billing_cycles')
        ->whereIn('facility_id', $facilityIds);
    
    // Apply date range filter to billing cycles if provided
    if ($dateRange && !empty($dateRange['from'])) {
        $query->where('created_at', '>=', $dateRange['from']);
    }
    if ($dateRange && !empty($dateRange['to'])) {
        $query->where('created_at', '<=', $dateRange['to']);
    }
    
    $totals = $query->groupBy('facility_id')
        ->select('facility_id',
            DB::raw('SUM(total_paid_amount) as total_paid'),
            DB::raw('SUM(balance_amount) as balance'))
        ->get();

    return $totals->keyBy('facility_id')->map(fn($item) => [
        'total_paid' => (float) $item->total_paid,
        'balance'    => (float) $item->balance,
    ]);
}

    // -------------------------------------------------------------------------
    // USERS
    // -------------------------------------------------------------------------

    /**
     * Get paginated list of platform users with filters
     */
    public function getUsersList(
        array $dateRange,
        ?string $status = null,
        ?string $search = null,
        int $perPage = 15,
        int $page = 1
    ): array {
        $query = $this->buildBaseUserQuery($dateRange, $status);
        
        if ($search) {
            $this->applyUserSearchConditions($query, $search);
        }
        
        $query->orderBy('created_at', 'desc');
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        
        return $this->transformUserPaginator($paginator);
    }

    /**
     * Build base query with date range and status filters
     */
    private function buildBaseUserQuery(array $dateRange, ?string $status): \Illuminate\Database\Eloquent\Builder
    {
        return User::query()
            ->when($dateRange['from'] ?? null, fn($q) => $q->where('created_at', '>=', $dateRange['from']))
            ->when($dateRange['to'] ?? null,   fn($q) => $q->where('created_at', '<=', $dateRange['to']))
            ->when($status, fn($q) => $q->where('status', $status));
    }

    /**
     * Apply search conditions to user query with optimized field matching
     */
    private function applyUserSearchConditions($query, string $search): void
    {
        $searchTerm = trim($search);
        
        $query->where(function ($sub) use ($searchTerm) {
            // Priority 1: Search by exact identifiers (fastest - uses indexes)
            $matched = $this->applyExactMatchSearch($sub, $searchTerm);
            
            // Priority 2: Search by name fields (fast - uses indexes)
            $this->applyNameSearch($sub, $searchTerm);
            
            // Priority 3: Search by partial/pattern matching (slowest - fallback)
            if (!$matched) {
                $this->applyPatternMatchSearch($sub, $searchTerm);
            }
        });
    }

    /**
     * Apply exact match search on indexed fields (fastest)
     * Returns true if an exact match condition was applied
     */
    private function applyExactMatchSearch($sub, string $searchTerm): bool
    {
        $matched = false;
        
        // Search by User ID
        if (is_numeric($searchTerm)) {
            $sub->orWhere('id', (int) $searchTerm);
            $matched = true;
        }
        
        // Search by UUID
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $searchTerm)) {
            $sub->orWhere('global_user_uuid', $searchTerm);
            $matched = true;
        }
        
        // Search by email (exact match using hash)
        if (filter_var($searchTerm, FILTER_VALIDATE_EMAIL)) {
            $emailHash = hash('sha256', strtolower($searchTerm));
            $sub->orWhere('email_hash', $emailHash);
            $matched = true;
        }
        
        // Search by phone number with + prefix
        if (preg_match('/^\+\d{8,15}$/', $searchTerm)) {
            $sub->orWhere('phone_hash', hash('sha256', $searchTerm));
            $matched = true;
        }
        
        // Search by phone number without + prefix
        elseif (preg_match('/^\d{9,15}$/', $searchTerm)) {
            $sub->orWhere('phone_hash', hash('sha256', '+' . $searchTerm));
            $matched = true;
        }
        
        return $matched;
    }

    /**
     * Apply name-based search (uses indexes if available)
     */
    private function applyNameSearch($sub, string $searchTerm): void
    {
        $sub->orWhere('first_name', 'like', "%{$searchTerm}%")
            ->orWhere('last_name', 'like', "%{$searchTerm}%")
            ->orWhere('display_name', 'like', "%{$searchTerm}%");
    }

    /**
     * Apply pattern matching for partial searches (slowest, used as fallback)
     */
    private function applyPatternMatchSearch($sub, string $searchTerm): void
    {
        $digitsOnly = preg_replace('/\D/', '', $searchTerm);
        
        // Phone number variations without exact format
        if (strlen($digitsOnly) >= 9 && strlen($digitsOnly) <= 15) {
            // Try with + prefix
            $sub->orWhere('phone_hash', hash('sha256', '+' . $digitsOnly));
            
            // Try without country code (last 9 digits for Uganda)
            if (strlen($digitsOnly) >= 12) {
                $withoutCountry = substr($digitsOnly, -9);
                $sub->orWhere('phone_hash', hash('sha256', '+' . $withoutCountry));
            }
        }
        
        // Partial phone search (last 4-6 digits) - performance heavy
        if (strlen($digitsOnly) >= 4 && strlen($digitsOnly) <= 6) {
            $sub->orWhere('phone_encrypted', 'like', "%{$digitsOnly}");
        }
    }

    /**
     * Transform paginated user results into formatted array
     */
    private function transformUserPaginator($paginator): array
    {
        $transformed = $paginator->getCollection()->map(function ($user) {
            return [
                'id'                => $user->id,
                'global_user_uuid'  => $user->global_user_uuid,
                'first_name'        => $user->first_name,
                'last_name'         => $user->last_name,
                'display_name'      => $user->display_name,
                'email'             => $this->decryptValue($user->email_encrypted),
                'phone'             => $this->decryptValue($user->phone_encrypted),
                'status'            => $user->status,
                'status_reason'     => $user->status_reason,
                'status_set_at'     => $this->formatTimestamp($user->status_set_at),
                'email_verified_at' => $this->formatTimestamp($user->email_verified_at),
                'last_login_at'     => $this->formatTimestamp($user->last_login_at),
                'created_at'        => $this->formatTimestamp($user->created_at),
            ];
        });
        
        return [
            'data'         => $transformed->all(),
            'current_page' => $paginator->currentPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
            'last_page'    => $paginator->lastPage(),
        ];
    }
    /**
     * Get user counts for dashboard summary.
     * 
     * @param Carbon|null $referenceDate  Date to use for "today", "this_week", "this_month" (defaults to now)
     */
    public function getUserCountsByDateRanges(?Carbon $referenceDate = null): array
    {
        $ref = $referenceDate ?? now();
        
        $todayStart = $ref->copy()->startOfDay();
        $todayEnd   = $ref->copy()->endOfDay();
        
        $weekStart = $ref->copy()->startOfWeek();
        $weekEnd   = $ref->copy()->endOfWeek();
        
        $monthStart = $ref->copy()->startOfMonth();
        $monthEnd   = $ref->copy()->endOfMonth();

        return [
            'total'      => User::count(),
            'today'      => User::whereBetween('created_at', [$todayStart, $todayEnd])->count(),
            'this_week'  => User::whereBetween('created_at', [$weekStart, $weekEnd])->count(),
            'this_month' => User::whereBetween('created_at', [$monthStart, $monthEnd])->count(),
            'active'     => User::where('status', 'active')->count(),
            'suspended'  => User::where('status', 'suspended')->count(),
            'banned'     => User::where('status', 'banned')->count(),
        ];
    }

    public function updateUserStatus(int $userId, string $status, ?string $reason, int $adminUserId): bool
    {
        $user = User::find($userId);
        if (!$user) return false;

        $user->status         = $status;
        $user->status_reason  = $reason;
        $user->status_set_at  = now();
        $user->status_set_by  = $adminUserId;

        return $user->save();
    }

    // -------------------------------------------------------------------------
    // PATIENTS
    // -------------------------------------------------------------------------

    public function getPatientsList(
        array $dateRange,
        ?string $status = null,
        ?string $search = null,
        int $perPage = 15,
        int $page = 1
    ): array {
        $query = Patient::query()
            ->with('user')
            ->when($dateRange['from'] ?? null, fn($q) => $q->where('created_at', '>=', $dateRange['from']))
            ->when($dateRange['to'] ?? null,   fn($q) => $q->where('created_at', '<=', $dateRange['to']))
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('first_name', 'like', "%{$search}%")
                                  ->orWhere('last_name', 'like', "%{$search}%")
                                  ->orWhere('display_name', 'like', "%{$search}%");
                    })->orWhere('medical_record_number_encrypted', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $transformed = $paginator->getCollection()->map(function ($patient) {
            $user = $patient->user;
            return [
                'id'                       => $patient->id,
                'patient_uuid'             => $patient->patient_uuid,
                'name'                     => $user ? ($user->display_name ?: trim($user->first_name . ' ' . $user->last_name)) : 'N/A',
                'email'                    => $user ? $this->decryptValue($user->email_encrypted) : null,
                'phone'                    => $user ? $this->decryptValue($user->phone_encrypted) : null,
                'date_of_birth'            => $patient->date_of_birth?->toDateString(),
                'biological_sex'           => $patient->biological_sex,
                'status'                   => $patient->status,
                'primary_insurance_provider' => $patient->primary_insurance_provider,
                'created_at'               => $this->formatTimestamp($patient->created_at),
            ];
        });

        return [
            'data'         => $transformed->all(),
            'current_page' => $paginator->currentPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
            'last_page'    => $paginator->lastPage(),
        ];
    }

    public function getTotalStaffCount(?array $dateRange = null): array
    {
        $staffQuery = DB::table('staff')
            ->when($dateRange['from'] ?? null, fn($q) => $q->where('created_at', '>=', $dateRange['from']))
            ->when($dateRange['to'] ?? null,   fn($q) => $q->where('created_at', '<=', $dateRange['to']));

        $totalStaff = $staffQuery->count();

        $assignedQuery = DB::table('facility_staff_roles')
            ->join('staff', 'facility_staff_roles.staff_id', '=', 'staff.id')
            ->where('facility_staff_roles.assignment_status', 'active')
            ->when($dateRange['from'] ?? null, fn($q) => $q->where('staff.created_at', '>=', $dateRange['from']))
            ->when($dateRange['to'] ?? null,   fn($q) => $q->where('staff.created_at', '<=', $dateRange['to']))
            ->distinct();

        $assignedStaff = $assignedQuery->count('facility_staff_roles.staff_id');

        return [
            'total'      => $totalStaff,
            'assigned'   => $assignedStaff,
            'unassigned' => $totalStaff - $assignedStaff,
        ];
    }

    /**
     * Get patient counts for dashboard summary.
     * 
     * @param Carbon|null $referenceDate  Date to use for "today", "this_week", "this_month" (defaults to now)
     */
    public function getPatientCountsByDateRanges(?Carbon $referenceDate = null): array
    {
        $ref = $referenceDate ?? now();
        
        $todayStart = $ref->copy()->startOfDay();
        $todayEnd   = $ref->copy()->endOfDay();
        
        $weekStart = $ref->copy()->startOfWeek();
        $weekEnd   = $ref->copy()->endOfWeek();
        
        $monthStart = $ref->copy()->startOfMonth();
        $monthEnd   = $ref->copy()->endOfMonth();

        return [
            'total'      => Patient::count(),
            'today'      => Patient::whereBetween('created_at', [$todayStart, $todayEnd])->count(),
            'this_week'  => Patient::whereBetween('created_at', [$weekStart, $weekEnd])->count(),
            'this_month' => Patient::whereBetween('created_at', [$monthStart, $monthEnd])->count(),
            'active'     => Patient::where('status', 'active')->count(),
            'inactive'   => Patient::where('status', 'inactive')->count(),
            'deceased'   => Patient::where('status', 'deceased')->count(),
        ];
    }

    /**
     * Safely format a timestamp to ISO 8601 string.
     */
    protected function formatTimestamp($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->toIso8601String();
        }

        try {
            return \Carbon\Carbon::parse($value)->toIso8601String();
        } catch (\Throwable $e) {
            Log::warning('Failed to parse timestamp.', ['value' => $value, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Safely decrypt a value, falling back to plain text if decryption fails
     * and the value appears to be a valid email address.
     */
    protected function decryptValue(?string $encrypted): ?string
    {
        if (empty($encrypted)) {
            return null;
        }

        try {
            return decrypt($encrypted);
        } catch (\Throwable $e) {
            if (filter_var($encrypted, FILTER_VALIDATE_EMAIL)) {
                return $encrypted;
            }

            Log::debug('Failed to decrypt value – returning placeholder.', [
                'error'        => $e->getMessage(),
                'value_length' => strlen($encrypted),
            ]);

            return '[encrypted]';
        }
    }
}