<?php

namespace App\Services\Module;

use App\Models\Module;
use Illuminate\Support\Collection;

class ModuleService
{
    /**
     * Get all active modules
     */
    public function getAllActive(): Collection
    {
        return Module::where('is_active', true)->orderBy('name')->get();
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
