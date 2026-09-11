<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance;

use App\Models\DsarRequest;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DsarRequestTest extends TestCase
{
    private function request(array $overrides = []): DsarRequest
    {
        $model = new DsarRequest();
        $model->forceFill(array_merge([
            'status' => 'received',
            'sla_due_at' => Carbon::now()->addWeekdays(5)->toDateTimeString(),
        ], $overrides));

        return $model;
    }

    /** @test */
    public function sla_is_five_working_days_skipping_weekends()
    {
        // Friday + 5 weekdays = next Friday (2026-09-11 is a Friday).
        $due = DsarRequest::slaDueAt(Carbon::parse('2026-09-11 10:00:00'));

        $this->assertSame('2026-09-18', $due->toDateString());
        $this->assertNotContains($due->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]);
    }

    /** @test */
    public function open_past_sla_is_overdue()
    {
        $this->assertTrue($this->request(['sla_due_at' => Carbon::now()->subHour()->toDateTimeString()])->isOverdue());
        $this->assertFalse($this->request(['sla_due_at' => Carbon::now()->addDay()->toDateTimeString()])->isOverdue());
    }

    /** @test */
    public function terminal_requests_are_never_overdue()
    {
        foreach (['fulfilled', 'rejected'] as $status) {
            $request = $this->request([
                'status' => $status,
                'sla_due_at' => Carbon::now()->subDays(30)->toDateTimeString(),
            ]);
            $this->assertTrue($request->isTerminal());
            $this->assertFalse($request->isOverdue(), "Terminal {$status} must not count as overdue.");
        }
    }
}
