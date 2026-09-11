<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance;

use App\Services\Compliance\BackupReviewService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BackupReviewServiceTest extends TestCase
{
    private BackupReviewService $review;

    protected function setUp(): void
    {
        parent::setUp();
        $this->review = new BackupReviewService();
    }

    /** @test */
    public function zero_byte_dumps_do_not_count_as_backups()
    {
        $rows = [
            ['taken_at' => Carbon::now()->subHour()->toDateTimeString(), 'bytes' => 0],
            ['taken_at' => Carbon::now()->subHours(10)->toDateTimeString(), 'bytes' => 1024],
        ];

        $hours = $this->review->hoursSinceLastUsable($rows);

        $this->assertNotNull($hours);
        $this->assertGreaterThanOrEqual(10, $hours);
        $this->assertFalse($this->review->isGap($hours));
    }

    /** @test */
    public function missing_or_stale_backups_are_gaps()
    {
        $this->assertTrue($this->review->isGap(null));
        $this->assertTrue($this->review->isGap(30.0));
        $this->assertFalse($this->review->isGap(5.0));
        $this->assertNull($this->review->hoursSinceLastUsable([]));
    }

    /** @test */
    public function failed_restore_tests_do_not_count()
    {
        $rows = [
            ['restore_tested_at' => Carbon::now()->subDays(2)->toDateTimeString(), 'restore_ok' => false],
            ['restore_tested_at' => Carbon::now()->subDays(40)->toDateTimeString(), 'restore_ok' => true],
        ];

        $days = $this->review->daysSinceRestoreTest($rows);

        $this->assertNotNull($days);
        $this->assertGreaterThanOrEqual(40, $days);
        $this->assertTrue($this->review->isRestoreTestOverdue($days));
        $this->assertTrue($this->review->isRestoreTestOverdue(null));
        $this->assertFalse($this->review->isRestoreTestOverdue(3.0));
    }
}
