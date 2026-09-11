<?php

declare(strict_types=1);

namespace App\Services\Interop;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DHIS2 aggregate push (Guidelines: HMIS dataset to eHMIS/DHIS2).
 *
 * Dry-run first: without credentials the exact payload is built and logged
 * but never posted, so mapping errors surface before MoH access exists.
 * Period format is strict YYYYMM - DHIS2 rejects anything else.
 */
class Dhis2Adapter
{
    public function isConfigured(): bool
    {
        $cfg = config('dhis2');

        return (bool) ($cfg['enabled'] ?? false)
            && ! empty($cfg['base_url'])
            && ! empty($cfg['username'])
            && ! empty($cfg['password'])
            && ! empty($cfg['org_unit']);
    }

    public function isDryRun(): bool
    {
        return ! $this->isConfigured() || (bool) config('dhis2.dry_run', true);
    }

    /**
     * @param list<array{dataElement: string, categoryOptionCombo?: string, value: int|string}> $values
     */
    public static function period(string $yearMonth): string
    {
        if (! preg_match('/^\d{4}(0[1-9]|1[0-2])$/', $yearMonth)) {
            throw new \InvalidArgumentException("Period must be YYYYMM, got '{$yearMonth}'.");
        }

        return $yearMonth;
    }

    /**
     * @param list<array{dataElement: string, categoryOptionCombo?: string, value: int|string}> $values
     */
    public function buildPayload(string $dataset, string $period, array $values): array
    {
        return [
            'dataSet' => $dataset,
            'completeDate' => now()->format('Y-m-d'),
            'period' => self::period($period),
            'orgUnit' => (string) config('dhis2.org_unit'),
            'dataValues' => array_map(fn ($v) => [
                'dataElement' => $v['dataElement'],
                'categoryOptionCombo' => $v['categoryOptionCombo'] ?? 'default',
                'value' => $v['value'],
            ], array_values($values)),
        ];
    }

    /**
     * @param list<array{dataElement: string, categoryOptionCombo?: string, value: int|string}> $values
     */
    public function push(string $dataset, string $period, array $values): array
    {
        $payload = $this->buildPayload($dataset, $period, $values);

        if ($this->isDryRun()) {
            Log::info('[DHIS2] Dry-run payload (not posted)', [
                'dataset' => $dataset, 'period' => $period, 'values' => count($values),
            ]);

            return ['success' => true, 'dry_run' => true, 'payload' => $payload];
        }

        $response = Http::withBasicAuth(
            (string) config('dhis2.username'),
            (string) config('dhis2.password')
        )->acceptJson()->timeout(30)->post(
            rtrim((string) config('dhis2.base_url'), '/') . '/api/dataValueSets',
            $payload
        );

        $data = $response->json() ?? [];

        if (! $response->successful()) {
            Log::error('[DHIS2] Push failed', ['status' => $response->status(), 'body' => $data]);
            throw new \RuntimeException('DHIS2 push failed: HTTP ' . $response->status());
        }

        Log::info('[DHIS2] Push accepted', ['dataset' => $dataset, 'period' => $period]);

        return ['success' => true, 'dry_run' => false, 'response' => $data];
    }
}
