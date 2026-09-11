<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance;

use App\Services\Compliance\RetentionService;
use App\Services\Contracts\AuditLogServiceInterface;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class RetentionServiceTest extends TestCase
{
    private function service(): RetentionService
    {
        return new RetentionService(Mockery::mock(AuditLogServiceInterface::class));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function schedule_covers_every_required_category()
    {
        $schedule = $this->service()->schedule();

        foreach (['medical_records', 'diagnostics', 'treatment_plans', 'billing', 'consents', 'audit_logs', 'messages', 'backups'] as $category) {
            $this->assertArrayHasKey($category, $schedule, "Missing retention rule: {$category}");
            $this->assertNotEmpty($schedule[$category]['basis']);
            $this->assertNotEmpty($schedule[$category]['method']);
        }

        // Health floor: nothing below the 5-year minimum except rolling ops.
        foreach ($schedule as $category => $rule) {
            if (($rule['method'] ?? '') === 'rolling_30_day') {
                continue;
            }
            $this->assertGreaterThanOrEqual(5, (int) $rule['years'], "{$category} below 5-year minimum");
        }
    }

    /** @test */
    public function due_dates_derive_from_anchor_plus_years()
    {
        $service = $this->service();

        $this->assertSame(
            '2031-01-01',
            $service->dueDate('billing', Carbon::parse('2024-01-01'))->toDateString()
        );
        $this->assertSame(
            '2024-01-31',
            $service->dueDate('backups', Carbon::parse('2024-01-01'))->toDateString()
        );
        $this->assertNull($service->dueDate('unknown_category', Carbon::now()));
    }

    /** @test */
    public function is_due_flags_only_expired_anchors()
    {
        $service = $this->service();

        $this->assertTrue($service->isDue('billing', Carbon::now()->subYears(8)));
        $this->assertFalse($service->isDue('billing', Carbon::now()->subYears(2)));
        $this->assertFalse($service->isDue('unknown_category', Carbon::now()->subYears(50)));
    }

    /** @test */
    public function review_reports_structure_without_deleting()
    {
        $report = $this->service()->review();

        $this->assertNotEmpty($report);
        foreach ($report as $category => $row) {
            $this->assertArrayHasKey('count', $row);
            $this->assertArrayHasKey('note', $row);
        }
    }

    /** @test */
    public function processor_certification_is_recorded_in_audit()
    {
        $audit = Mockery::mock(AuditLogServiceInterface::class);
        $audit->shouldReceive('createAuditLog')->once()->with(Mockery::on(function ($data) {
            return str_contains(json_encode($data), 'TestVendor');
        }))->andReturn(['success' => true]);

        $service = new RetentionService($audit);
        $result = $service->certifyProcessorDeletion('TestVendor', 'hosting backups', '2026-01-01');

        $this->assertTrue($result['success']);
    }
}
