<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Diagnosis;
use App\Services\Interop\Dhis2Adapter;
use Illuminate\Console\Command;

/**
 * Monthly HMIS aggregate push (due every 7th per Guidelines).
 * Counts diagnoses by code for the period and posts a dataValueSet
 * (or dry-runs it when DHIS2 credentials are absent).
 */
class HmisPushCommand extends Command
{
    protected $signature = 'hmis:push {--period= : YYYYMM, defaults to last month} {--dataset=opd_summary}';

    protected $description = 'Push monthly diagnosis aggregates to DHIS2 (dry-run without credentials).';

    public function handle(Dhis2Adapter $adapter): int
    {
        $period = $this->option('period') ?: now()->subMonth()->format('Ym');
        $dataset = (string) ($this->option('dataset') ?: 'opd_summary');
        $datasetUid = (string) config("dhis2.datasets.{$dataset}", $dataset);

        try {
            $period = Dhis2Adapter::period($period);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $year = (int) substr($period, 0, 4);
        $month = (int) substr($period, 4, 2);

        $counts = Diagnosis::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->selectRaw('diagnosis_code, COUNT(*) c')
            ->groupBy('diagnosis_code')
            ->pluck('c', 'diagnosis_code');

        // Data-element mapping lives in config once DHIS2 UIDs are provisioned;
        // until then the code itself is the element key (dry-run only).
        // Uncoded rows are reported, never silently dropped.
        $values = [];
        $uncoded = 0;
        foreach ($counts as $code => $count) {
            if (! IcdCode::looksIcd10((string) $code)) {
                $uncoded += (int) $count;
                continue;
            }
            $values[] = ['dataElement' => (string) $code, 'value' => (int) $count];
        }
        if ($uncoded > 0) {
            $this->warn("{$uncoded} diagnoses lack valid ICD-10 codes - flagged for coding review.");
        }

        $result = $adapter->push($datasetUid, $period, $values);

        $this->info($result['dry_run'] ?? false
            ? 'Dry-run: ' . count($values) . ' values built, nothing posted.'
            : 'Pushed ' . count($values) . ' values to DHIS2.');

        return self::SUCCESS;
    }
}
