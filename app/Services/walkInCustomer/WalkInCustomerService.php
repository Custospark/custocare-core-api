<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class WalkInCustomerService
{
    /**
     * A single global system identity for walk-in flows.
     * NOTE: Use config() in production to avoid hardcoding.
     */
    private const GLOBAL_WALKIN_KEY = 'SYSTEM-WALKIN-USER';
    private const SYSTEM_USER_COUNTRY_CODE = 'SYS';

    /**
     * Create or fetch the ONE global system user used as the anchor identity.
     * This user is never used directly for clinical accuracy; it is used to create facility-scoped walk-in patients.
     */
    public function getOrCreateGlobalSystemUser(): object
    {
        $hash = hash('sha256', self::GLOBAL_WALKIN_KEY);

        $user = DB::table('users')->where('national_id_hash', $hash)->first();
        if ($user) return $user;

        $userId = DB::table('users')->insertGetId([
            'global_user_uuid' => (string) Str::uuid(),
            'national_id_hash' => $hash,
            'national_id_encrypted' => base64_encode(self::GLOBAL_WALKIN_KEY), // replace with AES encryption helper
            'national_id_country_code' => self::SYSTEM_USER_COUNTRY_CODE,

            'identity_state' => 'verified',
            'identity_verified_at' => now(),
            'identity_verification_method' => 'system',

            'data_residency_region' => 'US',
            'created_from_facility_id' => null,

            'first_name' => 'System',
            'last_name' => 'WalkIn',
            'display_name' => 'System Walk-In Identity',

            'dob' => '1900-01-01',
            'gender' => 'other',

            'metadata' => json_encode([
                'is_system_user' => true,
                'system_user_type' => 'walk_in_customer',
                'immutable' => true,
                'purpose' => 'Anchor identity for facility-scoped walk-in patients',
            ]),

            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('users')->where('id', $userId)->first();
    }

    /**
     * Ensure the facility has exactly one walk-in patient.
     * Concurrency-safe: locks the mapping row to prevent duplicates under simultaneous clicks.
     */
    public function getOrCreateFacilityWalkInPatient(int $facilityId, ?int $staffId = null): array
    {
        return DB::transaction(function () use ($facilityId, $staffId) {
            $facility = DB::table('facilities')->where('id', $facilityId)->first();
            if (!$facility) throw new ModelNotFoundException("Facility not found");

            $systemUser = $this->getOrCreateGlobalSystemUser();

            // Lock mapping row to avoid double creation on concurrent calls
            $mapping = DB::table('facility_walkin_customers')
                ->where('facility_id', $facilityId)
                ->lockForUpdate()
                ->first();

            if ($mapping) {
                $patient = DB::table('patients')->where('id', $mapping->patient_id)->first();

                return [
                    'facility_id' => $facilityId,
                    'system_user_id' => (int) $mapping->system_user_id,
                    'patient_id' => (int) $mapping->patient_id,
                    'patient_uuid' => $patient->patient_uuid ?? null,
                    'display_name' => 'Walk-in Customer',
                    'mode' => 'existing',
                ];
            }

            // Facility-specific MRN for traceability (not a real MRN)
            $mrn = 'WALKIN-' . $facility->facility_code . '-' . $facilityId;

            // Create facility-scoped patient linked to global system user
            $patientId = DB::table('patients')->insertGetId([
                'patient_uuid' => (string) Str::uuid(),
                'user_id' => $systemUser->id,

                'medical_record_number_hash' => hash('sha256', $mrn),
                'medical_record_number_encrypted' => base64_encode($mrn), // replace with AES

                // Required by your schema
                'date_of_birth' => '1900-01-01',
                'biological_sex' => 'unknown',
                'gender_identity' => 'prefer_not_to_say',
                'privacy_flags' => json_encode([]),

                // Operational defaults for anonymous usage
                'default_consent_level' => 'minimal',
                'portal_access_enabled' => false,
                'payment_responsibility' => 'self_pay',

                // Consider 'active' if you want included in production stats.
                'status' => 'test_patient',

                'created_by_staff_id' => $staffId,
                'updated_by_staff_id' => $staffId,

                'metadata' => json_encode([
                    'walk_in_customer' => true,
                    'facility_id' => $facilityId,
                    'note' => 'Reusable facility-scoped anonymous patient',
                ]),

                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('facility_walkin_customers')->insert([
                'facility_id' => $facilityId,
                'system_user_id' => $systemUser->id,
                'patient_id' => $patientId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $patient = DB::table('patients')->where('id', $patientId)->first();

            return [
                'facility_id' => $facilityId,
                'system_user_id' => (int) $systemUser->id,
                'patient_id' => (int) $patientId,
                'patient_uuid' => $patient->patient_uuid ?? null,
                'display_name' => 'Walk-in Customer',
                'mode' => 'created',
            ];
        });
    }

    /**
     * Create a full walk-in checkout session:
     * - facility walk-in patient (get/create)
     * - visit (create)
     * - billing cycle (create)
     *
     * This is what your UI button should call.
     */
    public function createWalkInSession(int $facilityId, ?int $staffId = null): array
    {
        return DB::transaction(function () use ($facilityId, $staffId) {
            $walkin = $this->getOrCreateFacilityWalkInPatient($facilityId, $staffId);

            // Create VISIT (minimal fields; align with your visits schema)
            $visitId = DB::table('visits')->insertGetId([
                'facility_id' => $facilityId,
                'patient_id' => $walkin['patient_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create BILLING CYCLE (draft cart header)
            $billingCycleId = DB::table('billing_cycles')->insertGetId([
                'billing_cycle_uuid' => (string) Str::uuid(),
                'facility_id' => $facilityId,
                'visit_id' => $visitId,
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

            return [
                'facility_id' => $facilityId,
                'walkin' => $walkin,
                'visit' => [
                    'visit_id' => (int) $visitId,
                ],
                'billing' => [
                    'billing_cycle_id' => (int) $billingCycleId,
                    'status' => 'draft',
                ],
                'ui_next' => [
                    'route' => '/pharmacy/checkout',
                    'params' => [
                        'billing_cycle_id' => (int) $billingCycleId,
                        'visit_id' => (int) $visitId,
                        'patient_id' => (int) $walkin['patient_id'],
                    ],
                ],
            ];
        });
    }

    /**
     * Upgrade a walk-in session to a real patient at checkout.
     *
     * What it does:
     * - Creates a REAL user + patient
     * - Migrates VISIT + BILLING CYCLE (and related patient_id fields) to the new patient
     * - Keeps full auditability and operational continuity
     */
    public function upgradeWalkInToRealPatient(
        int $billingCycleId,
        int $facilityId,
        array $patientInput,
        ?int $staffId = null
    ): array {
        return DB::transaction(function () use ($billingCycleId, $facilityId, $patientInput, $staffId) {

            // 1) Load billing cycle + visit (lock to prevent concurrent upgrades)
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

            // 2) Verify this is currently tied to facility walk-in patient
            $mapping = DB::table('facility_walkin_customers')
                ->where('facility_id', $facilityId)
                ->first();

            if (!$mapping || (int)$mapping->patient_id !== (int)$cycle->patient_id) {
                // Prevent accidentally upgrading a real patient session
                abort(422, 'This checkout session is not using the facility walk-in customer.');
            }

            // 3) Create REAL USER
            // NOTE: Your users schema requires national_id_hash/encrypted not null.
            // If your real world flow allows “phone-only registration”, you should relax those constraints.
            // For now, we generate a pseudo key from phone/email (still hashed+encrypted).
            $phone = $patientInput['phone'] ?? null;
            $email = $patientInput['email'] ?? null;
            $firstName = $patientInput['first_name'] ?? 'Unknown';
            $lastName = $patientInput['last_name'] ?? 'Unknown';

            if (!$phone && !$email) {
                abort(422, 'Provide at least phone or email to upgrade walk-in customer.');
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

                // Contact hashes (optional but recommended)
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
                    'upgraded_at' => now()->toISOString(),
                    'facility_id' => $facilityId,
                ]),

                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 4) Create REAL PATIENT
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

            // 5) MIGRATE VISIT + BILLING CYCLE to new patient
            DB::table('visits')->where('id', $visit->id)->update([
                'patient_id' => $patientId,
                'updated_at' => now(),
            ]);

            DB::table('billing_cycles')->where('id', $billingCycleId)->update([
                'patient_id' => $patientId,
                'updated_by_staff_id' => $staffId,
                'updated_at' => now(),
            ]);

            /**
             * 6) MIGRATE dependent records that also store patient_id.
             * Add more updates here as your domain grows.
             *
             * IMPORTANT: keep it facility/visit scoped to avoid corrupting other sessions.
             */
            DB::table('prescriptions')->where('visit_id', $visit->id)->update([
                'patient_id' => $patientId,
                'updated_at' => now(),
            ]);

            DB::table('medication_dispenses')->where('visit_id', $visit->id)->update([
                'patient_id' => $patientId,
                'updated_at' => now(),
            ]);

            // invoice_line_items does not store patient_id in your schema; it links to billing_cycle_id.
            // So it stays consistent automatically.

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
