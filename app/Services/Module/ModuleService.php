<?php

namespace App\Services\Module;

use App\Models\Module;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ModuleService
{
    /**
     * Get all active modules
     */
public function getAllActive(): Collection
{
    $query = Module::where('is_active', true)
        ->orderBy('name');

    // Only super_admin sees platform_administration
    $user = Auth::user();
    if (!$user || !$user->hasRole('super_admin')) {
        $query->where('code', '!=', 'platform_administration');
    }

    return $query->get();
}

    /**
     * Create a new module
     */
    public function create(array $data): Module
    {
        return Module::create($data);
    }

    /**
     * Update an existing module
     */
    public function update(Module $module, array $data): Module
    {
        $module->update($data);
        return $module;
    }

    /**
     * Soft delete or deactivate a module
     */
    public function deactivate(Module $module): void
    {
        $module->update(['is_active' => false]);
    }
}
