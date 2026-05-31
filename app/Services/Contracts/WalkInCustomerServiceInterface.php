<?php

namespace App\Services\Contracts;

interface WalkInCustomerServiceInterface
{
    /**
     * Create a walk-in session (patient + visit + billing cycle).
     *
     * @param  int  $facilityId
     * @param  int|null  $staffId
     * @return array{facility_id: int, walkin: array, visit: mixed, billing: mixed, ui_next: array}
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException  Facility not found.
     * @throws \Illuminate\Validation\ValidationException            Visit limit reached.
     * @throws \RuntimeException                                     Walk-in patient cannot be resolved.
     */
    public function createWalkInSession(int $facilityId, ?int $staffId): array;

    /**
     * Upgrade a walk-in session to a real patient record.
     *
     * @param  int  $billingCycleId
     * @param  int  $facilityId
     * @param  array<string, mixed>  $patientData
     * @param  int|null  $staffId
     * @return array
     */
    public function upgradeWalkInToRealPatient(
        int $billingCycleId,
        int $facilityId,
        array $patientData,
        ?int $staffId,
    ): array;

    /**
     * Return or create the facility-level walk-in patient record.
     *
     * @param  int  $facilityId
     * @param  int|null  $staffId
     * @return array{walkin: mixed, patient: mixed}
     */
    public function getOrCreateFacilityWalkInPatient(int $facilityId, ?int $staffId): array;
}
