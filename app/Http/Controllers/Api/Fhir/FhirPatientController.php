<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Fhir;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;

/**
 * Minimal FHIR R4 stubs (Guidelines: HL7/FHIR exchange readiness).
 *
 * Deliberately narrow: identifiers never leave as raw values (UUIDs +
 * hashes only), clinical content stays behind the existing auth wall.
 * Full resource coverage arrives with the NHIE integration phase.
 */
class FhirPatientController extends Controller
{
    public function show(string $id): JsonResponse
    {
        $patient = Patient::where('patient_uuid', $id)->firstOrFail();

        return response()->json([
            'resourceType' => 'Patient',
            'id' => $patient->patient_uuid,
            'active' => $patient->status === 'active',
            'gender' => $this->mapGender($patient->biological_sex),
            'birthDate' => $patient->date_of_birth?->format('Y-m-d'),
            'identifier' => [
                [
                    'system' => 'https://custocare.health/fhir/mrn-hash',
                    'value' => $patient->medical_record_number_hash,
                ],
            ],
        ]);
    }

    private function mapGender(?string $sex): string
    {
        return match ($sex) {
            'male' => 'male',
            'female' => 'female',
            default => 'unknown',
        };
    }
}
