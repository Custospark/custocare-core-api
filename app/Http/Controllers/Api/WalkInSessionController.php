<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\walkInCustomer\WalkInCustomerService;
use Illuminate\Http\Request;

class WalkInSessionController extends Controller
{
    public function __construct(private readonly WalkInCustomerService $service)
    {
    }

    /**
     * Staff clicks "Walk-In Customer" → system returns an active checkout session:
     * - facility walk-in patient
     * - visit_id created
     * - billing_cycle_id created (draft)
     */
    public function createSession(Request $request, int $facilityId)
    {
        // Adjust based on your auth: staff id may not be request->user()->id
        $staffId = $request->user()?->id;

        $payload = $this->service->createWalkInSession($facilityId, $staffId);

        return response()->json($payload, 201);
    }

    /**
     * Upgrade Walk-In to Real Patient at checkout:
     * - Create real user/patient from provided info
     * - Migrate visit + billing cycle to the new patient
     */
    public function upgrade(Request $request, int $billingCycleId)
    {
        $staffId = $request->user()?->id;

        $validated = $request->validate([
            'facility_id' => ['required', 'integer'],

            // minimum identity capture
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:200'],

            // patient clinical basics (optional but recommended)
            'date_of_birth' => ['nullable', 'date'],
            'biological_sex' => ['nullable', 'in:male,female,intersex,unknown'],
            'gender_identity' => ['nullable', 'string', 'max:50'],

            'country_code' => ['nullable', 'string', 'max:3'],
            'data_residency_region' => ['nullable', 'string', 'max:10'],
        ]);

        $facilityId = (int)$validated['facility_id'];

        $payload = $this->service->upgradeWalkInToRealPatient(
            billingCycleId: $billingCycleId,
            facilityId: $facilityId,
            patientInput: $validated,
            staffId: $staffId
        );

        return response()->json($payload, 200);
    }
}
