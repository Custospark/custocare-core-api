<?php

namespace App\Policies;

use App\Models\MessageReceipt;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class MessageReceiptPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any message receipts.
     *
     * @param User $user
     * @return Response|bool
     */
    public function viewAny(User $user): Response|bool
    {
        // In production, implement role-based or permission-based checks
        // Example: return $user->hasPermission('view_message_receipts');
        
        // For now, allow any authenticated user to view receipts
        return $user !== null;
    }

    /**
     * Determine whether the user can view the message receipt.
     *
     * @param User $user
     * @param MessageReceipt $messageReceipt
     * @return Response|bool
     */
    public function view(User $user, MessageReceipt $messageReceipt): Response|bool
    {
        // Allow viewing if user is the recipient
        if ($messageReceipt->recipient_type === 'staff' && 
            $messageReceipt->recipient_id === $user->id) {
            return true;
        }
        
        // Allow viewing if user is the message sender (if sender is staff)
        // This requires a Message model with sender relationship
        
        // Allow admin users
        if ($user->hasRole('admin')) {
            return true;
        }
        
        return Response::deny('You do not have permission to view this receipt.');
    }

    /**
     * Determine whether the user can create message receipts.
     *
     * @param User $user
     * @return Response|bool
     */
    public function create(User $user): Response|bool
    {
        // Typically, receipts are created automatically by the system
        // Only allow system or admin users to create receipts manually
        return $user->hasRole('admin') || $user->hasRole('system');
    }

    /**
     * Determine whether the user can update the message receipt.
     *
     * @param User $user
     * @param MessageReceipt $messageReceipt
     * @return Response|bool
     */
    public function update(User $user, MessageReceipt $messageReceipt): Response|bool
    {
        // Only allow system or admin users to update receipts
        // Regular users should use the specific status update endpoints
        return $user->hasRole('admin') || $user->hasRole('system');
    }

    /**
     * Determine whether the user can delete the message receipt.
     *
     * @param User $user
     * @param MessageReceipt $messageReceipt
     * @return Response|bool
     */
    public function delete(User $user, MessageReceipt $messageReceipt): Response|bool
    {
        // Only allow admin users to delete receipts
        // System might delete old receipts via scheduled tasks
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can mark the receipt as delivered.
     *
     * @param User $user
     * @param MessageReceipt $messageReceipt
     * @return Response|bool
     */
    public function markAsDelivered(User $user, MessageReceipt $messageReceipt): Response|bool
    {
        // Typically done by the system when message is delivered
        // Could also be done by message sender or admin
        return $user->hasRole('admin') || $user->hasRole('system');
    }

    /**
     * Determine whether the user can mark the receipt as read.
     *
     * @param User $user
     * @param MessageReceipt $messageReceipt
     * @return Response|bool
     */
    public function markAsRead(User $user, MessageReceipt $messageReceipt): Response|bool
    {
        // Allow if user is the recipient
        if ($messageReceipt->recipient_type === 'staff' && 
            $messageReceipt->recipient_id === $user->id) {
            return true;
        }
        
        // Allow admin users
        if ($user->hasRole('admin')) {
            return true;
        }
        
        return Response::deny('Only the recipient can mark a receipt as read.');
    }

    /**
     * Determine whether the user can mark the receipt as acknowledged.
     *
     * @param User $user
     * @param MessageReceipt $messageReceipt
     * @return Response|bool
     */
    public function markAsAcknowledged(User $user, MessageReceipt $messageReceipt): Response|bool
    {
        // Allow if user is the recipient
        if ($messageReceipt->recipient_type === 'staff' && 
            $messageReceipt->recipient_id === $user->id) {
            return true;
        }
        
        // Allow admin users
        if ($user->hasRole('admin')) {
            return true;
        }
        
        return Response::deny('Only the recipient can acknowledge a receipt.');
    }

    /**
     * Determine whether the user can view receipts for a specific message.
     *
     * @param User $user
     * @param int $messageId
     * @return Response|bool
     */
    public function viewByMessage(User $user, int $messageId): Response|bool
    {
        // In production, check if user has permission to view the message
        // For now, allow admin users
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view receipts for a specific recipient.
     *
     * @param User $user
     * @param string $recipientType
     * @param int $recipientId
     * @return Response|bool
     */
    public function viewByRecipient(User $user, string $recipientType, int $recipientId): Response|bool
    {
        // Allow if user is viewing their own receipts
        if ($recipientType === 'staff' && $recipientId === $user->id) {
            return true;
        }
        
        // Allow admin users
        if ($user->hasRole('admin')) {
            return true;
        }
        
        return Response::deny('You can only view your own receipts.');
    }
}