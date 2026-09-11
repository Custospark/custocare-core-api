<?php

declare(strict_types=1);

namespace App\Services\Clinical;

/**
 * Plausibility gate for vital signs (Uganda Clinical Guidelines spine).
 *
 * Form-request min/max rules catch survival-impossible numbers. This check
 * catches CLINICALLY impossible combinations that pass those rules and must
 * never reach the record: inverted blood pressure, unrecordable SpO2 bands,
 * unit-confused temperatures, impossible BMI. Errors block save; warnings
 * ride along for clinician review.
 */
class ClinicalRangeCheck
{
    /**
     * @param array<string, mixed> $data
     * @return array{valid: bool, errors: list<string>, warnings: list<string>}
     */
    public function validate(array $data): array
    {
        $errors = [];
        $warnings = [];

        $temp = $this->number($data, 'temperature');
        if ($temp !== null) {
            $celsius = ($data['temperature_unit'] ?? 'celsius') === 'celsius'
                ? $temp
                : ($temp - 32) * 5 / 9;
            if ($celsius < 30 || $celsius > 43) {
                $errors[] = 'Temperature is outside any survivable band - check the value or unit.';
            } elseif ($celsius < 35 || $celsius >= 38) {
                $warnings[] = 'Temperature outside normal band - clinical review advised.';
            }
        }

        $sys = $this->number($data, 'systolic_bp');
        $dia = $this->number($data, 'diastolic_bp');
        if ($sys !== null && $dia !== null) {
            if ($dia >= $sys) {
                $errors[] = 'Diastolic pressure cannot equal or exceed systolic pressure.';
            }
            if ($sys - $dia > 150) {
                $warnings[] = 'Pulse pressure is extreme - verify the reading.';
            }
        }

        $spo2 = $this->number($data, 'oxygen_saturation');
        if ($spo2 !== null) {
            if ($spo2 < 50) {
                $errors[] = 'SpO2 below any recordable band - check probe placement or value.';
            } elseif ($spo2 < 90) {
                $warnings[] = 'Hypoxemia - urgent clinical review.';
            }
        }

        $hr = $this->number($data, 'heart_rate');
        if ($hr !== null && ($hr < 20 || $hr > 250)) {
            $errors[] = 'Heart rate is outside any recordable band.';
        }

        $rr = $this->number($data, 'respiratory_rate');
        if ($rr !== null && ($rr < 4 || $rr > 80)) {
            $errors[] = 'Respiratory rate is outside any recordable band.';
        }

        $height = $this->number($data, 'height');
        $weight = $this->number($data, 'weight');
        if ($height !== null && $weight !== null && $height > 0) {
            $heightM = ($data['height_unit'] ?? 'cm') === 'cm' ? $height / 100 : $height * 0.0254;
            $weightKg = ($data['weight_unit'] ?? 'kg') === 'kg' ? $weight : $weight * 0.453592;
            $bmi = $weightKg / ($heightM * $heightM);
            if ($bmi < 10 || $bmi > 80) {
                $errors[] = 'Height/weight imply an impossible BMI - check values or units.';
            }
        }

        return ['valid' => $errors === [], 'errors' => $errors, 'warnings' => $warnings];
    }

    private function number(array $data, string $key): ?float
    {
        if (! isset($data[$key]) || $data[$key] === '' || $data[$key] === null) {
            return null;
        }

        return is_numeric($data[$key]) ? (float) $data[$key] : null;
    }
}
