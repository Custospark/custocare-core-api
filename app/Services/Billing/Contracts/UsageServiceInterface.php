<?php

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

interface UsageServiceInterface
{
    public function getStaffCount(int $facilityId): int;
    public function getDepartmentCount(int $facilityId): int;
    public function getVisitsCount(int $facilityId): int;
    public function getAll(int $facilityId): array;
}
