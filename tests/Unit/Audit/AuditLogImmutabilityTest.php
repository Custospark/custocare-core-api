<?php

declare(strict_types=1);

namespace Tests\Unit\Audit;

use App\Models\AuditLog;
use Tests\TestCase;

class AuditLogImmutabilityTest extends TestCase
{
    private function log(array $overrides = []): AuditLog
    {
        $model = new AuditLog();
        $model->forceFill(array_merge(['legal_hold_flag' => false], $overrides));

        return $model;
    }

    /** @test */
    public function updates_are_always_blocked()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('immutable');

        AuditLog::blockMutation($this->log(), 'update');
    }

    /** @test */
    public function deletes_are_blocked_under_legal_hold()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('legal hold');

        AuditLog::blockMutation($this->log(['legal_hold_flag' => true]), 'delete');
    }

    /** @test */
    public function deletes_pass_without_legal_hold()
    {
        AuditLog::blockMutation($this->log(), 'delete');

        $this->assertTrue(true);
    }
}
