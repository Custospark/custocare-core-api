<?php

declare(strict_types=1);

namespace App\Services\Compliance;

/**
 * Deterministic PII anonymization for training-environment refreshes.
 *
 * Same input always yields the same fake value (stable across refreshes so
 * relational integrity survives), and outputs are recognizably fake
 * (example.invalid domains, 0770000000-series phones) so training data can
 * never be mistaken for real records or used for contact.
 */
class Anonymizer
{
    public static function name(?string $value, string $salt = ''): string
    {
        $hash = substr(hash('sha256', 'name|' . $salt . '|' . (string) $value), 0, 8);

        return 'Training User ' . strtoupper($hash);
    }

    public static function email(?string $value, string $salt = ''): string
    {
        $hash = substr(hash('sha256', 'email|' . $salt . '|' . (string) $value), 0, 12);

        return "training-{$hash}@example.invalid";
    }

    public static function phone(?string $value, string $salt = ''): string
    {
        $hash = hash('sha256', 'phone|' . $salt . '|' . (string) $value);
        $digits = preg_replace('/\D/', '', $hash) ?? '';
        $digits = str_pad($digits, 7, '0');

        return '0770' . substr($digits, 0, 7);
    }

    public static function identifier(?string $value, string $salt = ''): string
    {
        return substr(hash('sha256', 'id|' . $salt . '|' . (string) $value), 0, 16);
    }
}
