<?php

namespace App\Services\WalkInCustomer;

use App\Models\Facility;
use App\Models\FacilityWalkinCustomer;
use App\Models\Patient;
use App\Models\User;
use App\Models\BillingCycle;
use App\Services\Billing\Contracts\PlanLimitServiceInterface;
use App\Services\Contracts\VisitServiceInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WalkInCustomerService implements \App\Services\Contracts\WalkInCustomerServiceInterface
{
    protected const SYSTEM_WALKIN_EMAIL = 'walkin@custocare.com';

    public function __construct(
        protected PlanLimitServiceInterface $planLimitService,
        protected VisitServiceInterface $visitService,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function createWalkInSession(int $facilityId, ?int $staffId): array
    {
        $facility = Facility::find($facilityId);
        if (! $facility) {
            throw new ModelNotFoundException("Facility with ID {$facilityId} not found.");
        }

        // Gate: enforce monthly visit limit BEFORE creating any records.
        $this->planLimitService->assertCanCreateVisit($facilityId);

        return DB::transaction(function () use ($facility, $facilityId, $staffId) {
            // 1. Get or create the facility walk-in patient.
            $walkinData = $this->resolveWalkinPatient($facility);

            // 2. Create a visit for the walk-in patient.
            $visitResult = $this->visitService->createVisit([
                'facility_id' => $facilityId,
                'patient_id'  => $walkinData['patient']->id,
                'visit_type'  => 'outpatient',
                'chief_complaints' => ['Walk-in registration'],
                'arrived_at'  => now()->toDateTimeString(),
                'current_phase' => 'registration',
                'is_walk_in'  => true,
                'status'      => 'active',
                'acuity_score' => 3,
            ], $staffId ?? 0);

            if (! $visitResult['success']) {
                throw new \RuntimeException($visitResult['message'] ?? 'Failed to create visit.');
            }

            $visit = $visitResult['data'];

            // 3. Create a billing cycle for this visit.
            $billingCycle = BillingCycle::create([
                'billing_cycle_uuid' => (string) Str::uuid(),
                'facility_id'        => $facilityId,
                'visit_id'           => $visit->id,
                'patient_id'         => $walkinData['patient']->id,
                'cycle_type'         => 'walk_in',
                'period_start'       => now(),
                'period_end'         => now()->addDays(1),
                'created_by_staff_id' => $staffId,
                'updated_by_staff_id' => $staffId,
            ]);

            // 4. Return session data shaped for WalkInSessionResource.
            return [
                'facility_id' => $facilityId,
                'walkin' => [
                    'facility_id'    => $facilityId,
                    'system_user_id' => $walkinData['system_user']->id,
                    'patient_id'     => $walkinData['patient']->id,
                    'patient_uuid'   => $walkinData['patient']->patient_uuid ?? null,
                    'display_name'   => $walkinData['patient']->name ?? 'Walk-in Patient',
                    'mode'           => $walkinData['mode'],
                ],
                'visit'   => $visit,
                'billing' => $billingCycle,
                'ui_next' => [
                    'route'  => 'pharmacy.dispense',
                    'params' => [
                        'billing_cycle_id' => $billingCycle->id,
                        'visit_id'         => $visit->id,
                        'patient_id'       => $walkinData['patient']->id,
                    ],
                ],
            ];
        });
    }

    /**
     * {@inheritDoc}
     */
    public function upgradeWalkInToRealPatient(
        int $billingCycleId,
        int $facilityId,
        array $patientData,
        ?int $staffId,
    ): array {
        // Stub — implement when upgrade flow is required.
        throw new \RuntimeException('Walk-in upgrade is not yet implemented.');
    }

    /**
     * {@inheritDoc}
     */
    public function getOrCreateFacilityWalkInPatient(int $facilityId, ?int $staffId): array
    {
        $facility = Facility::findOrFail($facilityId);
        $data = $this->resolveWalkinPatient($facility);

        return [
            'walkin'  => $data['walkin'],
            'patient' => $data['patient'],
        ];
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * Resolve (get or create) the facility-level walk-in patient.
     *
     * @return array{walkin: FacilityWalkinCustomer, patient: Patient, system_user: User, mode: string}
     */
    protected function resolveWalkinPatient(Facility $facility): array
    {
        $existing = FacilityWalkinCustomer::where('facility_id', $facility->id)->first();

        if ($existing) {
            $patient = Patient::findOrFail($existing->patient_id);
            $user    = User::findOrFail($existing->system_user_id);

            return [
                'walkin'       => $existing,
                'patient'      => $patient,
                'system_user'  => $user,
                'mode'         => 'existing',
            ];
        }

        // Create a system walk-in user if one does not exist.
        $systemUser = User::firstOrCreate(
            ['email' => self::SYSTEM_WALKIN_EMAIL],
            [
                'name'              => 'System Walk-in',
                'password'          => bcrypt(Str::random(32)),
                'email_verified_at' => now(),
            ],
        );

        // Create the facility-specific walk-in patient.
        $patient = Patient::create([
            'user_id'          => $systemUser->id,
            'facility_id'      => $facility->id,
            'patient_number'   => 'WALKIN-' . $facility->id . '-' . Str::random(6),
            'name'             => 'Walk-in Patient (' . $facility->name . ')',
            'status'           => 'active',
        ]);

        $walkin = FacilityWalkinCustomer::create([
            'facility_id'    => $facility->id,
            'system_user_id' => $systemUser->id,
            'patient_id'     => $patient->id,
        ]);

        return [
            'walkin'       => $walkin,
            'patient'      => $patient,
            'system_user'  => $systemUser,
            'mode'         => 'created',
        ];
    }
}
