<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Billing\Gateways\GatewayManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Billing health monitor - read-only, never writes.
 *
 * Reports the four revenue-risk signals in one place:
 *   1. Recent billing/payment/gateway errors in laravel.log (last hour)
 *   2. Stuck pending payments (gateway > 30 min, manual > 24 h)
 *   3. Failed queue jobs (broadcasts, mails, receipts that never delivered)
 *   4. Gateway availability right now
 *
 * Exit 0 = clean, 1 = issues found (cron-friendly).
 */
class BillingMonitorCommand extends Command
{
    protected $signature = 'billing:monitor {--hours=1 : Log window in hours} {--stuck-minutes=30 : Age after which a pending gateway payment is stuck}';

    protected $description = 'Report billing health: log errors, stuck payments, failed jobs, gateway status.';

    public function handle(GatewayManager $manager): int
    {
        $issues = 0;

        // ── 1. Log sweep ──────────────────────────────────────────────
        $hours = max(1, (int) $this->option('hours'));
        $since = Carbon::now()->subHours($hours);
        $logFile = storage_path('logs/laravel.log');
        $billingErrors = [];

        if (is_file($logFile)) {
            $handle = fopen($logFile, 'r');
            if ($handle) {
                // Read tail only - logs can be hundreds of MB.
                fseek($handle, 0, SEEK_END);
                $pos = max(0, ftell($handle) - 512 * 1024);
                fseek($handle, $pos);
                $chunk = stream_get_contents($handle) ?: '';
                fclose($handle);

                foreach (preg_split('/\R/', $chunk) as $line) {
                    if (! str_contains($line, '.ERROR')) {
                        continue;
                    }
                    if (! preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) {
                        continue;
                    }
                    try {
                        if (Carbon::parse($m[1])->lt($since)) {
                            continue;
                        }
                    } catch (\Throwable) {
                        continue;
                    }
                    if (preg_match('/billing|payment|pesapal|gateway|subscription|invoice|receipt/i', $line)) {
                        $billingErrors[] = substr($line, 0, 220);
                    }
                }
            }
        }

        $this->info('log errors (billing, last ' . $hours . 'h): ' . count($billingErrors));
        foreach (array_slice($billingErrors, 0, 5) as $error) {
            $this->line('  - ' . $error);
        }
        if (count($billingErrors) > 5) {
            $this->line('  ... and ' . (count($billingErrors) - 5) . ' more');
        }
        if ($billingErrors !== []) {
            $issues++;
        }

        // ── 2. Stuck pending payments ─────────────────────────────────
        try {
            $stuckMinutes = max(5, (int) $this->option('stuck-minutes'));
            $stuckGateway = Payment::where('status', 'pending')
                ->where('method', 'gateway')
                ->where('created_at', '<', Carbon::now()->subMinutes($stuckMinutes))
                ->count();
            $stuckManual = Payment::where('status', 'pending')
                ->where('method', '!=', 'gateway')
                ->where('created_at', '<', Carbon::now()->subDay())
                ->count();

            $this->info("stuck pending: gateway {$stuckGateway} (> {$stuckMinutes}m), manual {$stuckManual} (> 24h)");
            if ($stuckGateway > 0 || $stuckManual > 0) {
                $issues++;
            }
        } catch (\Throwable $e) {
            $this->warn('stuck-pending check skipped: ' . $e->getMessage());
        }

        // ── 3. Queue depth + failures ─────────────────────────────────
        try {
            $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
            $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            $this->info("queue: {$pending} pending, {$failed} failed");

            if (Schema::hasTable('failed_jobs') && $failed > 0) {
                $latest = DB::table('failed_jobs')->orderByDesc('failed_at')->first();
                if ($latest) {
                    $this->line('  latest failure: ' . substr((string) ($latest->connection ?? '?'), 0, 40)
                        . ' @ ' . ($latest->failed_at ?? '?'));
                }
                $issues++;
            }
        } catch (\Throwable $e) {
            $this->warn('queue check skipped: ' . $e->getMessage());
        }

        // ── 4. Gateway availability ───────────────────────────────────
        // NONE is only an issue when a gateway is supposed to be live
        // (local dev intentionally runs with gateways disabled).
        try {
            $available = $manager->available();
            $this->info('gateways available: ' . ($available === [] ? 'NONE' : implode(',', $available)));
            $shouldBeLive = (bool) config('billing_gateways.pesapal.enabled', false);
            if ($available === [] && $shouldBeLive) {
                $this->warn('PesaPal is enabled in config but no driver reports available.');
                $issues++;
            }
        } catch (\Throwable $e) {
            $this->warn('gateway check skipped: ' . $e->getMessage());
        }

        if ($issues > 0) {
            $this->error("billing:monitor found {$issues} issue group(s).");

            return self::FAILURE;
        }

        $this->info('billing:monitor clean.');

        return self::SUCCESS;
    }
}
