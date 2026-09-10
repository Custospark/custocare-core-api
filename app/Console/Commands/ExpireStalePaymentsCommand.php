<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Billing\Gateways\GatewayService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Expire abandoned pending gateway payments so re-initiation opens.
 *
 * Safety first: each candidate is live-verified with the gateway before
 * expiring. If the money actually arrived, it approves instead - an expired
 * payment never swallows completed money.
 */
class ExpireStalePaymentsCommand extends Command
{
    protected $signature = 'payments:expire-stale {--minutes=1440 : Age in minutes after which a pending gateway payment is stale}';

    protected $description = 'Verify-then-expire abandoned pending gateway payments.';

    public function handle(GatewayService $gatewayService): int
    {
        $minutes = max(15, (int) $this->option('minutes'));
        $cutoff = Carbon::now()->subMinutes($minutes);

        $candidates = Payment::where('status', 'pending')
            ->where('method', 'gateway')
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->limit(100)
            ->get();

        $expired = 0;
        $approved = 0;

        foreach ($candidates as $payment) {
            try {
                $result = $gatewayService->verifyPendingPayment($payment);
                if (($result['status'] ?? '') === 'approved') {
                    $approved++;
                    continue;
                }
                $gatewayService->cancelPendingPayment($payment, 'expired_stale_ttl');
                $expired++;
            } catch (\Throwable $e) {
                Log::warning('[ExpireStale] Skipped payment', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("stale sweep: {$approved} approved, {$expired} expired, " . count($candidates) . ' checked.');

        return self::SUCCESS;
    }
}
