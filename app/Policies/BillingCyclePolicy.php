<?php

namespace App\Policies;

use App\Models\BillingCycle;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BillingCyclePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any billing cycles.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('billing_cycles.view');
    }

    /**
     * Determine whether the user can view the billing cycle.
     *
     * @param User $user
     * @param BillingCycle $billingCycle
     * @return bool
     */
    public function view(User $user, BillingCycle $billingCycle): bool
    {
        // Users can view billing cycles they created
        if ($billingCycle->created_by_staff_id && $user->id === $billingCycle->created_by_staff_id) {
            return true;
        }
        
        // Facility staff can view billing cycles in their facility
        if ($user->facility_id && $user->facility_id === $billingCycle->facility_id) {
            return $user->hasPermission('billing_cycles.view');
        }
        
        // Administrators can view all billing cycles
        return $user->hasPermission('billing_cycles.view_all');
    }

    /**
     * Determine whether the user can create billing cycles.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('billing_cycles.create');
    }

    /**
     * Determine whether the user can update the billing cycle.
     *
     * @param User $user
     * @param BillingCycle $billingCycle
     * @return bool
     */
    public function update(User $user, BillingCycle $billingCycle): bool
    {
        // Check if billing cycle is in a modifiable state
        $nonModifiableStatuses = ['paid_in_full', 'written_off', 'charity_care', 'collections'];
        if (in_array($billingCycle->billing_status, $nonModifiableStatuses)) {
            return false;
        }
        
        // Users can update billing cycles they created
        if ($billingCycle->created_by_staff_id && $user->id === $billingCycle->created_by_staff_id) {
            return $user->hasPermission('billing_cycles.update');
        }
        
        // Facility staff can update billing cycles in their facility
        if ($user->facility_id && $user->facility_id === $billingCycle->facility_id) {
            return $user->hasPermission('billing_cycles.update');
        }
        
        // Administrators can update all billing cycles
        return $user->hasPermission('billing_cycles.update_all');
    }

    /**
     * Determine whether the user can delete the billing cycle.
     *
     * @param User $user
     * @param BillingCycle $billingCycle
     * @return bool
     */
    public function delete(User $user, BillingCycle $billingCycle): bool
    {
        // Check if billing cycle is in a deletable state
        $nonDeletableStatuses = ['submitted_to_insurance', 'partially_paid', 'paid_in_full', 'collections'];
        if (in_array($billingCycle->billing_status, $nonDeletableStatuses)) {
            return false;
        }
        
        // Users can delete billing cycles they created (within time limit)
        if ($billingCycle->created_by_staff_id && $user->id === $billingCycle->created_by_staff_id) {
            $timeLimit = config('billing_cycles.delete_time_limit', 24); // hours
            $createdTime = $billingCycle->created_at;
            $timeDiff = now()->diffInHours($createdTime);
            
            if ($timeDiff <= $timeLimit) {
                return $user->hasPermission('billing_cycles.delete');
            }
        }
        
        // Facility managers can delete billing cycles in their facility
        if ($user->facility_id && $user->facility_id === $billingCycle->facility_id) {
            return $user->hasPermission('billing_cycles.delete') && 
                   $user->hasRole('facility_manager');
        }
        
        // Administrators can delete all billing cycles
        return $user->hasPermission('billing_cycles.delete_all');
    }

    /**
     * Determine whether the user can restore the billing cycle.
     *
     * @param User $user
     * @param BillingCycle $billingCycle
     * @return bool
     */
    public function restore(User $user, BillingCycle $billingCycle): bool
    {
        return $user->hasPermission('billing_cycles.restore') || 
               $user->hasPermission('billing_cycles.restore_all');
    }

    /**
     * Determine whether the user can permanently delete the billing cycle.
     *
     * @param User $user
     * @param BillingCycle $billingCycle
     * @return bool
     */
    public function forceDelete(User $user, BillingCycle $billingCycle): bool
    {
        return $user->hasPermission('billing_cycles.force_delete');
    }

    /**
     * Determine whether the user can update billing status.
     *
     * @param User $user
     * @param BillingCycle $billingCycle
     * @param string $status
     * @return bool
     */
    public function updateStatus(User $user, BillingCycle $billingCycle, string $status): bool
    {
        // Special permissions for specific status transitions
        $restrictedTransitions = [
            'collections' => 'billing_cycles.mark_collections',
            'written_off' => 'billing_cycles.write_off',
            'charity_care' => 'billing_cycles.mark_charity',
            'disputed' => 'billing_cycles.mark_disputed',
        ];
        
        if (isset($restrictedTransitions[$status])) {
            return $user->hasPermission($restrictedTransitions[$status]);
        }
        
        // Default permission for other status updates
        return $this->update($user, $billingCycle);
    }

    /**
     * Determine whether the user can record payments.
     *
     * @param User $user
     * @param BillingCycle $billingCycle
     * @return bool
     */
    public function recordPayment(User $user, BillingCycle $billingCycle): bool
    {
        // Check if billing cycle can accept payments
        $nonPayableStatuses = ['draft', 'pending_review', 'written_off', 'charity_care'];
        if (in_array($billingCycle->billing_status, $nonPayableStatuses)) {
            return false;
        }
        
        // Facility billing staff can record payments
        if ($user->facility_id && $user->facility_id === $billingCycle->facility_id) {
            return $user->hasPermission('billing_cycles.record_payment');
        }
        
        // Administrators can record payments for all billing cycles
        return $user->hasPermission('billing_cycles.record_payment_all');
    }

    /**
     * Determine whether the user can view financial reports.
     *
     * @param User $user
     * @param BillingCycle $billingCycle
     * @return bool
     */
    public function viewFinancial(User $user, BillingCycle $billingCycle): bool
    {
        // Facility staff can view financials for their facility
        if ($user->facility_id && $user->facility_id === $billingCycle->facility_id) {
            return $user->hasPermission('billing_cycles.view_financial');
        }
        
        // Administrators and financial staff can view all financials
        return $user->hasPermission('billing_cycles.view_financial_all');
    }

    /**
     * Determine whether the user can send statements.
     *
     * @param User $user
     * @param BillingCycle $billingCycle
     * @return bool
     */
    public function sendStatement(User $user, BillingCycle $billingCycle): bool
    {
        // Only billing cycles that are billable can have statements sent
        $billableStatuses = ['pending_submission', 'submitted_to_insurance', 'partially_paid'];
        if (!in_array($billingCycle->billing_status, $billableStatuses)) {
            return false;
        }
        
        // Facility billing staff can send statements
        if ($user->facility_id && $user->facility_id === $billingCycle->facility_id) {
            return $user->hasPermission('billing_cycles.send_statement');
        }
        
        return false;
    }
}