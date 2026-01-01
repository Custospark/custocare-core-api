<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class PatientIdGenerator
{
    /**
     * Generate a public, human-readable patient UUID
     * Format: PT-01HZX9F7K3M2
     */
    public static function generatePatientUuid(): string
    {
        return 'PT-' . Str::ulid()->toBase32();
    }

    /**
     * Generate a system Medical Record Number (MRN) and its hash
     *
     * - MRN: human-readable alphanumeric, 10 characters
     * - Hash: SHA256 for database uniqueness and security
     */
    public static function generateMedicalRecordNumber(): string
{
    // Human-readable MRN (10 chars, uppercase, avoid confusing letters)
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $mrn = '';
    $maxIndex = strlen($alphabet) - 1;

    for ($i = 0; $i < 10; $i++) {
        $mrn .= $alphabet[random_int(0, $maxIndex)];
    }

    return $mrn;
}

}
