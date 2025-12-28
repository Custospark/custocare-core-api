<?php

namespace App\Policies;

use App\Models\InvoiceLineItem;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvoiceLineItemPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('invoice_line_items.view') || 
               $user->hasRole(['billing_manager', 'admin']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InvoiceLineItem $invoiceLineItem): bool
    {
        return $user->hasPermission('invoice_line_items.view') || 
               $user->hasRole(['billing_manager', 'admin']) ||
               $this->isOwner($user, $invoiceLineItem);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('invoice_line_items.create') || 
               $user->hasRole(['billing_manager', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InvoiceLineItem $invoiceLineItem): bool
    {
        // Cannot update billed or paid items
        if (in_array($invoiceLineItem->line_item_status, [
            InvoiceLineItem::STATUS_BILLED,
            InvoiceLineItem::STATUS_PAID,
        ])) {
            return false;
        }
        
        return $user->hasPermission('invoice_line_items.update') || 
               $user->hasRole(['billing_manager', 'admin']) ||
               $this->isOwner($user, $invoiceLineItem);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InvoiceLineItem $invoiceLineItem): bool
    {
        // Cannot delete billed or paid items
        if (in_array($invoiceLineItem->line_item_status, [
            InvoiceLineItem::STATUS_BILLED,
            InvoiceLineItem::STATUS_PAID,
        ])) {
            return false;
        }
        
        return $user->hasPermission('invoice_line_items.delete') || 
               $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, InvoiceLineItem $invoiceLineItem): bool
    {
        return $user->hasPermission('invoice_line_items.restore') || 
               $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, InvoiceLineItem $invoiceLineItem): bool
    {
        return $user->hasPermission('invoice_line_items.force_delete') || 
               $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can update the status of the model.
     */
    public function updateStatus(User $user, InvoiceLineItem $invoiceLineItem): bool
    {
        return $user->hasPermission('invoice_line_items.update_status') || 
               $user->hasRole(['billing_manager', 'admin']);
    }

    /**
     * Determine whether the user can mark the model as reviewed.
     */
    public function markAsReviewed(User $user, InvoiceLineItem $invoiceLineItem): bool
    {
        return $user->hasPermission('invoice_line_items.review') || 
               $user->hasRole(['coding_specialist', 'billing_manager', 'admin']);
    }

    /**
     * Determine whether the user can validate the model for billing.
     */
    public function validateForBilling(User $user, InvoiceLineItem $invoiceLineItem): bool
    {
        return $user->hasPermission('invoice_line_items.validate') || 
               $user->hasRole(['billing_manager', 'admin']);
    }

    /**
     * Determine whether the user can view the audit trail of the model.
     */
    public function viewAuditTrail(User $user, InvoiceLineItem $invoiceLineItem): bool
    {
        return $user->hasPermission('invoice_line_items.audit') || 
               $user->hasRole(['auditor', 'admin']);
    }

    /**
     * Check if the user is the owner of the invoice line item.
     */
    private function isOwner(User $user, InvoiceLineItem $invoiceLineItem): bool
    {
        // Check if user created the line item
        if ($invoiceLineItem->created_by_staff_id && $user->id === $invoiceLineItem->created_by_staff_id) {
            return true;
        }
        
        // Check if user performed the service
        if ($invoiceLineItem->staff_performed_id && $user->id === $invoiceLineItem->staff_performed_id) {
            return true;
        }
        
        return false;
    }
}