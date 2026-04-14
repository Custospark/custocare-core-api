<?php

namespace App\Services\WalkInCustomer;

use App\Models\Facility;
use App\Models\FacilityWalkinCustomer;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\User;
use App\Support\HealthcareIdGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WalkInCustomerService 
{
    /**
     * A single global system identity for walk-in flows.
     */
    private const GLOBAL_WALKIN_KEY = 'SYSTEM-WALKIN-USER';
    private const SYSTEM_USER_COUNTRY_CODE = 'SYS';

    /**
     * Get or create the global system user.
     */
    public function getOrCreateGlobalSystemUser(): User
    {
        $hash = hash('sha256', self::GLOBAL_WALKIN_KEY);
        
        try {
            $user = User::updateOrCreate(
                ['national_id_hash' => $hash],
                [
                    'global_user_uuid' => Str::uuid()->toString(),
                    'national_id_encrypted' => base64_encode(self::GLOBAL_WALKIN_KEY),
                    'national_id_country_code' => self::SYSTEM_USER_COUNTRY_CODE,
                    
                    'identity_state' => 'verified',
                    'identity_verified_at' => now(),
                    'identity_verification_method' => 'system',
                    
                    'data_residency_region' => 'US',
                    'allowed_processing_regions' => ['US'],
                    'created_from_facility_id' => null,
                    
                    'first_name' => 'System',
                    'last_name' => 'WalkIn',
                    'display_name' => 'Walk-in Patient',
                    
                    'dob' => '1900-01-01',
                    'gender' => 'other',
                    
                    'requires_password_change' => false,
                    'mfa_enabled' => false,
                    'failed_login_attempts' => 0,
                    
                    'metadata' => [
                        'is_system_user' => true,
                        'system_user_type' => 'walk_in_patient',
                        'immutable' => true,
                        'purpose' => 'Anchor identity for facility-scoped walk-in patients',
                    ],
                ]
            );
            
            Log::info('System user ensured', [
                'user_id' => $user->id,
                'global_user_uuid' => $user->global_user_uuid,
                'was_recently_created' => $user->wasRecentlyCreated ?? 'unknown',
            ]);
            
            return $user;
            
        } catch (\Exception $e) {
            Log::error('Failed to ensure system user exists', [
                'error' => $e->getMessage(),
                'hash' => $hash,
            ]);
            
            throw $e;
        }
    }

    /**
     * Get or create the global system user with transaction.
     */
    public function getOrCreateGlobalSystemUserWithTransaction(): User
    {
        return DB::transaction(function () {
            $hash = hash('sha256', self::GLOBAL_WALKIN_KEY);
            
            $user = User::firstOrCreate(
                ['national_id_hash' => $hash],
                [
                    'global_user_uuid' => Str::uuid()->toString(),
                    'national_id_encrypted' => base64_encode(self::GLOBAL_WALKIN_KEY),
                    'national_id_country_code' => self::SYSTEM_USER_COUNTRY_CODE,
                    
                    'identity_state' => 'verified',
                    'identity_verified_at' => now(),
                    'identity_verification_method' => 'system',
                    
                    'data_residency_region' => 'US',
                    'allowed_processing_regions' => ['US'],
                    'created_from_facility_id' => null,
                    
                    'first_name' => 'System',
                    'last_name' => 'WalkIn',
                    'display_name' => 'Walk-in Patient',
                    
                    'dob' => '1900-01-01',
                    'gender' => 'other',
                    
                    'requires_password_change' => false,
                    'mfa_enabled' => false,
                    'failed_login_attempts' => 0,
                    
                    'metadata' => [
                        'is_system_user' => true,
                        'system_user_type' => 'walk_in_patient',
                        'immutable' => true,
                        'purpose' => 'Anchor identity for facility-scoped walk-in patients',
                        'created_via' => 'SystemUserService::getOrCreateGlobalSystemUserWithTransaction',
                        // 'created_at' => now()->toISOString(),
                    ],
                ]
            );
            
            if ($user->wasRecentlyCreated) {
                Log::info('Created new system user via firstOrCreate', [
                    'user_id' => $user->id,
                    'global_user_uuid' => $user->global_user_uuid,
                ]);
            } else {
                Log::info('System user already existed.', [
                    'user_id' => $user->id,
                    'global_user_uuid' => $user->global_user_uuid,
                ]);
            }
            
            return $user;
        });
    }
    
    /**
     * Check if a user is the system walk-in user.
     */
    public function isSystemWalkInUser(User $user): bool
    {
        $hash = hash('sha256', self::GLOBAL_WALKIN_KEY);
        return $user->national_id_hash === $hash;
    }
    
    /**
     * Get the system user by UUID.
     */
    public function getSystemUserByUuid(string $uuid): ?User
    {
        $hash = hash('sha256', self::GLOBAL_WALKIN_KEY);
        
        return User::where('global_user_uuid', $uuid)
            ->where('national_id_hash', $hash)
            ->first();
    }

    /**
     * Get or create facility walk-in patient.
     */
    public function getOrCreateFacilityWalkInPatient(int $facilityId, ?int $staffId = null): array
    {
        return DB::transaction(function () use ($facilityId, $staffId) {
            $facility = Facility::find($facilityId);
            if (!$facility) {
                throw new ModelNotFoundException("Facility not found");
            }

            $systemUser = $this->getOrCreateGlobalSystemUser();

            $mapping = FacilityWalkinCustomer::where('facility_id', $facilityId)
                ->lockForUpdate()
                ->first();

            if ($mapping) {
                $patient = Patient::find($mapping->patient_id);

                return [
                    'facility_id' => $facilityId,
                    'system_user_id' => (int) $mapping->system_user_id,
                    'patient_id' => (int) $mapping->patient_id,
                    'patient_uuid' => $patient->patient_uuid ?? null,
                    'display_name' => 'Walk-in Patient',
                    'mode' => 'existing',
                ];
            }

            $mrn = 'WALKIN-' . $facility->facility_code . '-' . $facilityId;

            $patient = Patient::create([
                'patient_uuid' => HealthcareIdGenerator::generateRandomCode('WKN'),
                'user_id' => $systemUser->id,

                'medical_record_number_hash' => hash('sha256', $mrn),
                'medical_record_number_encrypted' => base64_encode($mrn),

                'date_of_birth' => '1900-01-01',
                'biological_sex' => 'unknown',
                'gender_identity' => 'prefer_not_to_say',
                'privacy_flags' => json_encode([]),

                'default_consent_level' => 'minimal',
                'portal_access_enabled' => false,
                'payment_responsibility' => 'self_pay',

                'status' => 'system_patient',

                'created_by_staff_id' => $staffId,
                'updated_by_staff_id' => $staffId,

                'metadata' => json_encode([
                    'walk_in_patient' => true,
                    'facility_id' => $facilityId,
                    'note' => 'Reusable facility-scoped anonymous patient',
                ]),
            ]);

            FacilityWalkinCustomer::create([
                'facility_id' => $facilityId,
                'system_user_id' => $systemUser->id,
                'patient_id' => $patient->id,
            ]);

            return [
                'facility_id' => $facilityId,
                'system_user_id' => (int) $systemUser->id,
                'patient_id' => (int) $patient->id,
                'patient_uuid' => $patient->patient_uuid ?? null,
                'display_name' => 'Walk-in Patient',
                'mode' => 'created',
            ];
        });
    }

    /**
     * Create a walk-in session.
     */
    public function createWalkInSession(int $facilityId, ?int $staffId = null): array
    {
        return DB::transaction(function () use ($facilityId, $staffId) {
            $walkin = $this->getOrCreateFacilityWalkInPatient($facilityId, $staffId);
            Log::info("Walk In User", $walkin);
            $staffId = Staff::where('user_id', Auth::id())->value('id');

            if (!$staffId) {
                abort(403, 'Authenticated user is not linked to a staff record.');
            }

            $visit = DB::table('visits')->insertGetId([
                'visit_uuid' => (string) Str::uuid(),
                'facility_id' => $facilityId,
                'patient_id' => $walkin['patient_id'],
                'visit_type' => 'outpatient',
                'acuity_score' => 3,
                'chief_complaints' => json_encode([]),
                'arrived_at' => now(),
                'current_phase' => 'registration',
                'assigned_staff_id'=>$staffId,
                'is_walk_in' => true,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $visitData = DB::table('visits')->find($visit);

            $billingCycle = DB::table('billing_cycles')->insertGetId([
                'billing_cycle_uuid' => HealthcareIdGenerator::generate('billing'),
                'facility_id' => $facilityId,
                'visit_id' => $visit,
                'patient_id' => $walkin['patient_id'],
                
                'cycle_type' => 'visit_based',
                'period_start' => now(),
                'billing_status' => 'draft',
                
                'total_amount_charged' => 0,
                'total_adjustments' => 0,
                'net_amount' => 0,
                
                'patient_responsibility_amount' => 0,
                'patient_payment_received' => 0,
                
                'created_by_staff_id' => $staffId,
                'updated_by_staff_id' => $staffId,
                
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $billingCycleData = DB::table('billing_cycles')->find($billingCycle);

            return [
                'facility_id' => $facilityId,
                'walkin' => $walkin,
                'visit' => $visitData,
                'billing' => $billingCycleData,
                'ui_next' => [
                    'route' => '/pharmacy/checkout',
                    'params' => [
                        'billing_cycle_id' => (int) $billingCycle,
                        'visit_id' => (int) $visit,
                        'patient_id' => (int) $walkin['patient_id'],
                    ],
                ],
            ];
        });
    }

    /**
     * Upgrade walk-in session to real patient.
     */
    public function upgradeWalkInToRealPatient(
        int $billingCycleId,
        int $facilityId,
        array $patientInput,
        ?int $staffId = null
    ): array {
        return DB::transaction(function () use ($billingCycleId, $facilityId, $patientInput, $staffId) {
            $cycle = DB::table('billing_cycles')
                ->where('id', $billingCycleId)
                ->where('facility_id', $facilityId)
                ->lockForUpdate()
                ->first();

            if (!$cycle) {
                throw new ModelNotFoundException("Billing cycle not found");
            }

            $visit = DB::table('visits')->where('id', $cycle->visit_id)->lockForUpdate()->first();
            if (!$visit) {
                throw new ModelNotFoundException("Visit not found");
            }

            $mapping = DB::table('facility_walkin_customers')
                ->where('facility_id', $facilityId)
                ->first();

            if (!$mapping || (int)$mapping->patient_id !== (int)$cycle->patient_id) {
                throw new \RuntimeException('This checkout session is not using the facility walk-in customer.');
            }

            $phone = $patientInput['phone'] ?? null;
            $email = $patientInput['email'] ?? null;
            $firstName = $patientInput['first_name'] ?? 'Unknown';
            $lastName = $patientInput['last_name'] ?? 'Unknown';

            if (!$phone && !$email) {
                throw new \RuntimeException('Provide at least phone or email to upgrade walk-in customer.');
            }

            $pseudoNationalId = 'REG-' . ($phone ?: $email) . '-' . Str::uuid();
            $nationalHash = hash('sha256', $pseudoNationalId);

            $userId = DB::table('users')->insertGetId([
                'global_user_uuid' => (string) Str::uuid(),
                'national_id_hash' => $nationalHash,
                'national_id_encrypted' => base64_encode($pseudoNationalId),
                'national_id_country_code' => $patientInput['country_code'] ?? 'UNK',

                'identity_state' => 'pending',
                'data_residency_region' => $patientInput['data_residency_region'] ?? 'US',
                'created_from_facility_id' => $facilityId,

                'phone_encrypted' => $phone ? base64_encode($phone) : null,
                'phone_hash' => $phone ? hash('sha256', $phone) : null,
                'email_encrypted' => $email ? base64_encode($email) : null,
                'email_hash' => $email ? hash('sha256', strtolower($email)) : null,

                'first_name' => $firstName,
                'last_name' => $lastName,
                'display_name' => trim($firstName . ' ' . $lastName),

                'dob' => $patientInput['dob'] ?? null,
                'gender' => $patientInput['gender'] ?? null,

                'created_by_staff_id' => $staffId,
                'updated_by_staff_id' => $staffId,

                'metadata' => json_encode([
                    'upgraded_from_walkin' => true,
                    // 'upgraded_at' => now()->toISOString(),
                    'facility_id' => $facilityId,
                ]),

                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $mrn = 'MRN-' . $facilityId . '-' . strtoupper(Str::random(10));

            $patientId = DB::table('patients')->insertGetId([
                'patient_uuid' => (string) Str::uuid(),
                'user_id' => $userId,

                'medical_record_number_hash' => hash('sha256', $mrn),
                'medical_record_number_encrypted' => base64_encode($mrn),

                'date_of_birth' => $patientInput['date_of_birth'] ?? '1900-01-01',
                'biological_sex' => $patientInput['biological_sex'] ?? 'unknown',
                'gender_identity' => $patientInput['gender_identity'] ?? null,

                'privacy_flags' => json_encode([]),

                'default_consent_level' => 'full',
                'portal_access_enabled' => true,

                'status' => 'active',

                'created_by_staff_id' => $staffId,
                'updated_by_staff_id' => $staffId,

                'metadata' => json_encode([
                    'created_from_walkin_upgrade' => true,
                    'facility_id' => $facilityId,
                    'source_billing_cycle_id' => $billingCycleId,
                ]),

                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('visits')->where('id', $visit->id)->update([
                'patient_id' => $patientId,
                'updated_at' => now(),
            ]);

            DB::table('billing_cycles')->where('id', $billingCycleId)->update([
                'patient_id' => $patientId,
                'updated_by_staff_id' => $staffId,
                'updated_at' => now(),
            ]);

            DB::table('prescriptions')->where('visit_id', $visit->id)->update([
                'patient_id' => $patientId,
                'updated_at' => now(),
            ]);

            DB::table('medication_dispenses')->where('visit_id', $visit->id)->update([
                'patient_id' => $patientId,
                'updated_at' => now(),
            ]);

            return [
                'facility_id' => $facilityId,
                'billing_cycle_id' => $billingCycleId,
                'visit_id' => (int) $visit->id,
                'upgraded' => true,
                'new_user_id' => (int) $userId,
                'new_patient_id' => (int) $patientId,
                'ui_next' => [
                    'route' => '/pharmacy/checkout',
                    'params' => [
                        'billing_cycle_id' => $billingCycleId,
                        'visit_id' => (int) $visit->id,
                        'patient_id' => (int) $patientId,
                    ],
                ],
            ];
        });
    }
}