<?php

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

interface AssignableModuleServiceInterface
{
    /**
     * Modules that may be granted via invitations/assignments for a facility,
     * scoped to the facility subscription plan (and owner administration when applicable).
     *
     * @return array{
     *     modules: \Illuminate\Support\Collection<int, \App\Models\Module>,
     *     allowed_module_codes: list<string>,
     *     plan_enabled_module_codes: list<string>,
     *     plan: array{slug: string|null, name: string|null}|null
     * }
     */
    public function getForFacility(int $facilityId, bool $includeOwnerAdministration): array;
}
