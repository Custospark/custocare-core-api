<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MessagePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_messages');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Message $message): bool
    {
        // Check if user is a participant in the conversation
        if ($user->isParticipantInConversation($message->conversation_id)) {
            return true;
        }

        // Check if user has permission to view all messages
        if ($user->hasPermission('view_all_messages')) {
            return true;
        }

        // For clinical messages, additional permissions may be required
        if ($message->is_clinical && !$user->hasPermission('view_clinical_messages')) {
            return false;
        }

        // Check PHI access
        if ($message->contains_phi && !$user->hasPermission('view_phi')) {
            return false;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_messages');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Message $message): bool
    {
        // Only allow updates within a certain time frame (e.g., 5 minutes)
        $isWithinEditWindow = $message->created_at->diffInMinutes(now()) <= 5;
        
        if (!$isWithinEditWindow) {
            return false;
        }

        // Only the sender can edit their own messages (except system messages)
        if ($message->sender_type !== 'system' && $message->sender_id === $user->id) {
            return true;
        }

        // Admins can edit messages
        if ($user->hasPermission('edit_any_message')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Message $message): bool
    {
        // System messages cannot be deleted
        if ($message->sender_type === 'system') {
            return false;
        }

        // Only the sender or admins can delete
        if ($message->sender_id === $user->id && $user->hasPermission('delete_own_messages')) {
            return true;
        }

        if ($user->hasPermission('delete_any_message')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Message $message): bool
    {
        return $user->hasPermission('restore_messages');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Message $message): bool
    {
        return $user->hasPermission('force_delete_messages');
    }

    /**
     * Determine whether the user can mark message as delivered.
     */
    public function markAsDelivered(User $user, Message $message): bool
    {
        // Only recipients or system can mark as delivered
        return $user->isRecipientOf($message) || $user->hasRole('system');
    }

    /**
     * Determine whether the user can acknowledge a message.
     */
    public function acknowledge(User $user, Message $message): bool
    {
        // Only recipients can acknowledge messages
        if (!$user->isRecipientOf($message)) {
            return false;
        }

        // Message must require acknowledgement
        if (!$message->requires_acknowledgement) {
            return false;
        }

        // Message must be in sent status
        if ($message->delivery_status !== 'sent') {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can view clinical messages.
     */
    public function viewClinical(User $user): bool
    {
        return $user->hasPermission('view_clinical_messages');
    }

    /**
     * Determine whether the user can view PHI.
     */
    public function viewPHI(User $user, Message $message): bool
    {
        if (!$message->contains_phi) {
            return true;
        }

        return $user->hasPermission('view_phi');
    }
}