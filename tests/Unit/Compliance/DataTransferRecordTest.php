<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance;

use App\Models\DataTransferRecord;
use Tests\TestCase;

class DataTransferRecordTest extends TestCase
{
    private function record(array $overrides = []): DataTransferRecord
    {
        $model = new DataTransferRecord();
        $model->forceFill(array_merge([
            'destination_country' => 'Kenya',
            'recipient' => 'Test Lab',
            'data_categories' => 'lab results',
            'legal_basis' => 'adequacy',
            'pdpo_authorisation_ref' => 'PDPO-2026-001',
            'moh_consent' => true,
            'status' => 'authorized',
        ], $overrides));

        return $model;
    }

    /** @test */
    public function fully_papered_transfer_is_authorized()
    {
        $this->assertTrue($this->record()->isAuthorized());
        $this->assertFalse($this->record()->isBlocked());
    }

    /** @test */
    public function missing_any_pillar_blocks_authorization()
    {
        // No PDPO reference.
        $this->assertFalse($this->record(['pdpo_authorisation_ref' => null])->isAuthorized());
        // No MoH consent.
        $this->assertFalse($this->record(['moh_consent' => false])->isAuthorized());
        // Prohibited basis can never authorize.
        $this->assertFalse($this->record(['legal_basis' => 'prohibited'])->isAuthorized());
        $this->assertTrue($this->record(['legal_basis' => 'prohibited'])->isBlocked());
    }

    /** @test */
    public function consent_basis_also_needs_paperwork()
    {
        $this->assertTrue($this->record(['legal_basis' => 'consent'])->isAuthorized());
        $this->assertFalse($this->record(['legal_basis' => 'consent', 'moh_consent' => false])->isAuthorized());
    }

    /** @test */
    public function blocked_status_wins_over_paperwork()
    {
        $record = $this->record(['status' => 'blocked']);

        $this->assertFalse($record->isAuthorized());
        $this->assertTrue($record->isBlocked());
    }
}
