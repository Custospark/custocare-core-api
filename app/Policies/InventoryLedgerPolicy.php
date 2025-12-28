<?php

namespace App\Policies;

use App\Models\InventoryLedger;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

/**
 * Policy for InventoryLedger model authorization.
 * Defines who can perform various actions on ledger entries.
 */
class InventoryLedgerPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param User $user
     * @return Response|bool
     */
    public function viewAny(User $user): Response|bool
    {
        return $user->hasPermission('inventory_ledger.view')
            ? Response::allow()
            : Response::deny('You do not have permission to view inventory ledger entries.');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param InventoryLedger $inventoryLedger
     * @return Response|bool
     */
    public function view(User $user, InventoryLedger $inventoryLedger): Response|bool
    {
        // Users can view ledger entries for facilities they have access to
        $hasFacilityAccess = $user->facilities->contains($inventoryLedger->facility_id);
        
        return $user->hasPermission('inventory_ledger.view') && $hasFacilityAccess
            ? Response::allow()
            : Response::deny('You do not have permission to view this inventory ledger entry.');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return Response|bool
     */
    public function create(User $user): Response|bool
    {
        return $user->hasPermission('inventory_ledger.create')
            ? Response::allow()
            : Response::deny('You do not have permission to create inventory ledger entries.');
    }

    /**
     * Determine whether the user can update the model.
     * Note: Ledger entries are typically immutable - only allow for corrections.
     *
     * @param User $user
     * @param InventoryLedger $inventoryLedger
     * @return Response|bool
     */
    public function update(User $user, InventoryLedger $inventoryLedger): Response|bool
    {
        // Only allow updates if entry is not verified
        if ($inventoryLedger->verified_at) {
            return Response::deny('Cannot update a verified ledger entry.');
        }
        
        // User needs specific permission and facility access
        $hasFacilityAccess = $user->facilities->contains($inventoryLedger->facility_id);
        
        return $user->hasPermission('inventory_ledger.correct') && $hasFacilityAccess
            ? Response::allow()
            : Response::deny('You do not have permission to update inventory ledger entries.');
    }

    /**
     * Determine whether the user can delete the model.
     * Note: Ledger entries should rarely be deleted.
     *
     * @param User $user
     * @param InventoryLedger $inventoryLedger
     * @return Response|bool
     */
    public function delete(User $user, InventoryLedger $inventoryLedger): Response|bool
    {
        // Never allow deletion of verified entries
        if ($inventoryLedger->verified_at) {
            return Response::deny('Cannot delete a verified ledger entry.');
        }
        
        // Check if this is the latest entry
        $latestEntry = InventoryLedger::where('facility_id', $inventoryLedger->facility_id)
            ->where('inventory_item_id', $inventoryLedger->inventory_item_id)
            ->latest('transaction_timestamp')
            ->first();
        
        if ($latestEntry && $latestEntry->id === $inventoryLedger->id) {
            return Response::deny('Cannot delete the most recent ledger entry for this inventory item.');
        }
        
        // Only super admins can delete ledger entries
        return $user->hasRole('super_admin') && $user->hasPermission('inventory_ledger.delete')
            ? Response::allow()
            : Response::deny('You do not have permission to delete inventory ledger entries.');
    }

    /**
     * Determine whether the user can verify the model.
     *
     * @param User $user
     * @param InventoryLedger $inventoryLedger
     * @return Response|bool
     */
    public function verify(User $user, InventoryLedger $inventoryLedger): Response|bool
    {
        // Cannot verify already verified entries
        if ($inventoryLedger->verified_at) {
            return Response::deny('This ledger entry has already been verified.');
        }
        
        // User must have facility access
        $hasFacilityAccess = $user->facilities->contains($inventoryLedger->facility_id);
        
        return $user->hasPermission('inventory_ledger.verify') && $hasFacilityAccess
            ? Response::allow()
            : Response::deny('You do not have permission to verify inventory ledger entries.');
    }

    /**
     * Determine whether the user can view inventory balances.
     *
     * @param User $user
     * @return Response|bool
     */
    public function viewBalance(User $user): Response|bool
    {
        return $user->hasPermission('inventory.balance.view')
            ? Response::allow()
            : Response::deny('You do not have permission to view inventory balances.');
    }

    /**
     * Determine whether the user can view audit trail.
     *
     * @param User $user
     * @return Response|bool
     */
    public function viewAuditTrail(User $user): Response|bool
    {
        return $user->hasPermission('inventory.audit.view')
            ? Response::allow()
            : Response::deny('You do not have permission to view inventory audit trail.');
    }
}