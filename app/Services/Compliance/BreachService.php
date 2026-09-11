<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\BreachIncident;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Breach workflow (Act Sec 23, Guidelines Sec 5.7/8.1).
 *
 * SLA clock: PDPO immediately on awareness; subjects within 24h when high
 * risk. Every transition is timestamped on the incident row - the row IS
 * the audit trail an inspector asks for.
 */
class BreachService
{
    public function report(array $data): BreachIncident
    {
        $incident = BreachIncident::create([
            'incident_uuid' => (string) Str::uuid(),
            'facility_id' => $data['facility_id'] ?? null,
            'reported_by_staff_id' => $data['reported_by_staff_id'] ?? null,
            'description' => $data['description'],
            'severity' => $data['severity'] ?? 'low',
            'status' => 'open',
            'affected_subjects_estimate' => $data['affected_subjects_estimate'] ?? 0,
            'detected_at' => $data['detected_at'] ?? now(),
            'is_drill' => $data['is_drill'] ?? false,
        ]);

        Log::warning('Breach incident reported', [
            'incident_id' => $incident->id,
            'severity' => $incident->severity,
            'drill' => $incident->is_drill,
        ]);

        return $incident->fresh();
    }

    public function markPdpoNotified(BreachIncident $incident): BreachIncident
    {
        $incident->update([
            'pdpo_notified_at' => now(),
            'status' => 'notified_pdpo',
        ]);

        return $incident->fresh();
    }

    public function markSubjectsNotified(BreachIncident $incident): BreachIncident
    {
        $incident->update([
            'subjects_notified_at' => now(),
            'status' => 'subjects_notified',
        ]);

        return $incident->fresh();
    }

    public function markContained(BreachIncident $incident, string $notes = ''): BreachIncident
    {
        $incident->update([
            'contained_at' => now(),
            'status' => 'contained',
            'remediation_notes' => $notes ?: $incident->remediation_notes,
        ]);

        return $incident->fresh();
    }

    public function close(BreachIncident $incident): BreachIncident
    {
        $incident->update(['status' => 'closed', 'closed_at' => now()]);

        return $incident->fresh();
    }

    /**
     * Overdue high-risk incidents: detected >= 24h ago, subjects not notified.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, BreachIncident>
     */
    public function overdueSubjectNotices()
    {
        return BreachIncident::where('severity', 'high')
            ->whereNull('subjects_notified_at')
            ->where('detected_at', '<', now()->subHours(24))
            ->where('is_drill', false)
            ->orderBy('detected_at')
            ->get();
    }
}
