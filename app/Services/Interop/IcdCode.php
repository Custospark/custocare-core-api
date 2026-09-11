<?php

declare(strict_types=1);

namespace App\Services\Interop;

/**
 * ICD-10 shape check for HMIS data quality (Guidelines: ICD coding).
 *
 * Advisory only: clinical entry is never blocked on coding shape (a wrong
 * guess at triage beats a refused save), but uncoded rows are counted in
 * every HMIS push so data quality is visible and improvable.
 */
class IcdCode
{
    public static function looksIcd10(?string $code): bool
    {
        if ($code === null) {
            return false;
        }

        $normalized = strtoupper(trim($code));

        return (bool) preg_match('/^[A-Z][0-9][0-9A-Z](\.[0-9A-Z]{1,4})?$/', $normalized);
    }

    public static function system(): string
    {
        return 'http://hl7.org/fhir/sid/icd-10';
    }
}
