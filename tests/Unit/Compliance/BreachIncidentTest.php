<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance;

use App\Models\BreachIncident;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BreachIncidentTest extends TestCase
{
    private function incident(array $overrides = []): BreachIncident
    {
        $model = new BreachIncident();
        $model->forceFill(array_merge([
            'severity' => 'high',
            'detected_at' => Carbon::now()->subHours(25)->toDateTimeString(),
            'subjects_notified_at' => null,
        ], $overrides));

        return $model;
    }

    /** @test */
    public function high_risk_unnotified_after_24h_is_overdue()
    {
        $this->assertTrue($this->incident()->isOverdueForSubjectNotice());
    }

    /** @test */
    public function notified_subjects_are_never_overdue()
    {
        $incident = $this->incident(['subjects_notified_at' => Carbon::now()->subHours(30)->toDateTimeString()]);

        $this->assertFalse($incident->isOverdueForSubjectNotice());
    }

    /** @test */
    public function low_severity_never_triggers_subject_sla()
    {
        $incident = $this->incident(['severity' => 'low']);

        $this->assertFalse($incident->isHighRisk());
        $this->assertFalse($incident->isOverdueForSubjectNotice());
    }

    /** @test */
    public function fresh_high_risk_still_has_time()
    {
        $incident = $this->incident(['detected_at' => Carbon::now()->subHours(2)->toDateTimeString()]);

        $this->assertTrue($incident->isHighRisk());
        $this->assertFalse($incident->isOverdueForSubjectNotice());
    }
}
