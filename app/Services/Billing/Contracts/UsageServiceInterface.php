<?php

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

interface UsageServiceInterface
{
    public function getStaffCount(int $facilityId): int;

    public function getDepartmentCount(int $facilityId): int;

    public function getVisitsCount(int $facilityId): int;

    /**
     * True when staff already has an active/on_leave assignment or pending invitation at the facility.
     */
    public function isStaffCountedTowardLimit(int $facilityId, int $staffId): bool;

    public function getAll(int $facilityId): array;
}
