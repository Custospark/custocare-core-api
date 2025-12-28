<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InventoryItemPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory_items.view_any');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->hasPermission('inventory_items.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('inventory_items.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->hasPermission('inventory_items.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InventoryItem $inventoryItem): bool
    {
        // Prevent deletion of items that are currently in use
        if ($inventoryItem->status === 'active') {
            return false;
        }
        
        return $user->hasPermission('inventory_items.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->hasPermission('inventory_items.restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, InventoryItem $inventoryItem): bool
    {
        // Only allow force delete for inactive items
        if ($inventoryItem->status === 'active') {
            return false;
        }
        
        return $user->hasPermission('inventory_items.force_delete');
    }

    /**
     * Determine whether the user can view controlled substances.
     */
    public function viewControlledSubstances(User $user): bool
    {
        return $user->hasPermission('inventory_items.view_controlled_substances');
    }

    /**
     * Determine whether the user can update controlled substances.
     */
    public function updateControlledSubstances(User $user, InventoryItem $inventoryItem): bool
    {
        if (!$inventoryItem->isControlledSubstance()) {
            return true;
        }
        
        return $user->hasPermission('inventory_items.update_controlled_substances');
    }
}