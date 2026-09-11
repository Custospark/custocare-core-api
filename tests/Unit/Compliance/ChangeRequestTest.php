<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance;

use App\Models\ChangeRequest;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ChangeRequestTest extends TestCase
{
    private function request(array $overrides = []): ChangeRequest
    {
        $model = new ChangeRequest();
        $model->forceFill(array_merge([
            'approved_by' => null,
            'approved_at' => null,
            'executed_at' => null,
        ], $overrides));

        return $model;
    }

    /** @test */
    public function unapproved_changes_cannot_execute()
    {
        $request = $this->request();

        $this->assertFalse($request->isApproved());
        $this->assertFalse($request->canExecute());
    }

    /** @test */
    public function approved_unexecuted_changes_can_execute_once()
    {
        $request = $this->request([
            'approved_by' => 'Oscar',
            'approved_at' => Carbon::now()->toDateTimeString(),
        ]);

        $this->assertTrue($request->isApproved());
        $this->assertTrue($request->canExecute());

        $request->forceFill(['executed_at' => Carbon::now()->toDateTimeString()]);

        $this->assertFalse($request->canExecute());
    }

    /** @test */
    public function half_signed_approval_does_not_count()
    {
        // Approver named but no timestamp (or vice versa) is not approval.
        $this->assertFalse($this->request(['approved_by' => 'Oscar'])->canExecute());
        $this->assertFalse($this->request(['approved_at' => Carbon::now()->toDateTimeString()])->canExecute());
    }
}
