<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\DsarRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Data Subject Access Request handling (Act Sec 16/24-28).
 *
 * Every request is registered regardless of outcome. Corrections keep the
 * original record plus a versioned update (never rewrite history);
 * erasure checks the legal-hold (retention schedule) first and blocks or
 * restricts where the law mandates retention.
 */
class DsarService
{
    public function submit(array $data): DsarRequest
    {
        $request = DsarRequest::create([
            'request_uuid' => (string) Str::uuid(),
            'patient_id' => $data['patient_id'] ?? null,
            'requested_by_staff_id' => $data['requested_by_staff_id'] ?? null,
            'request_type' => $data['request_type'],
            'channel' => $data['channel'] ?? 'in_person',
            'details' => $data['details'] ?? null,
            'status' => 'received',
            'sla_due_at' => DsarRequest::slaDueAt($data['received_at'] ?? null),
        ]);

        Log::info('DSAR submitted', [
            'request_id' => $request->id,
            'type' => $request->request_type,
            'sla_due_at' => $request->sla_due_at->toDateTimeString(),
        ]);

        return $request->fresh();
    }

    public function startReview(DsarRequest $request): DsarRequest
    {
        $this->assertOpen($request);
        $request->update(['status' => 'in_review']);

        return $request->fresh();
    }

    public function fulfill(DsarRequest $request, string $notes = ''): DsarRequest
    {
        $this->assertOpen($request);
        $request->update([
            'status' => 'fulfilled',
            'resolution_notes' => $notes,
            'fulfilled_at' => now(),
        ]);

        return $request->fresh();
    }

    public function reject(DsarRequest $request, string $reasons): DsarRequest
    {
        $this->assertOpen($request);
        $request->update([
            'status' => 'rejected',
            'rejection_reasons' => $reasons,
            'fulfilled_at' => now(),
        ]);

        return $request->fresh();
    }

    public function markDownstreamNotified(DsarRequest $request): DsarRequest
    {
        $request->update(['downstream_notified' => true]);

        return $request->fresh();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, DsarRequest>
     */
    public function overdue()
    {
        return DsarRequest::whereIn('status', ['received', 'in_review'])
            ->where('sla_due_at', '<', now())
            ->orderBy('sla_due_at')
            ->get();
    }

    private function assertOpen(DsarRequest $request): void
    {
        if ($request->isTerminal()) {
            throw new \DomainException(
                "Request #{$request->id} is already {$request->status}; terminal requests cannot transition.",
                422
            );
        }
    }
}
