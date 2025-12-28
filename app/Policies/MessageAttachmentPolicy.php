<?php

namespace App\Policies;

use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MessageAttachmentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Users can view attachments if they have message viewing permissions
        return $user->can('view-messages') || $user->hasRole('healthcare_provider');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MessageAttachment $messageAttachment): bool
    {
        // Users can view attachment if they can view the related message
        return $user->can('view', $messageAttachment->message) || 
               $user->hasRole('healthcare_provider');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only authorized users can create attachments
        return $user->can('create-messages') || 
               $user->hasRole('healthcare_provider') ||
               $user->hasRole('administrator');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MessageAttachment $messageAttachment): bool
    {
        // Only administrators can update attachments
        return $user->hasRole('administrator') || 
               $user->id === $messageAttachment->message->sender_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MessageAttachment $messageAttachment): bool
    {
        // Only administrators or message sender can delete attachments
        return $user->hasRole('administrator') || 
               $user->id === $messageAttachment->message->sender_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MessageAttachment $messageAttachment): bool
    {
        // Only administrators can restore deleted attachments
        return $user->hasRole('administrator');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MessageAttachment $messageAttachment): bool
    {
        // Only administrators can permanently delete attachments
        return $user->hasRole('administrator');
    }

    /**
     * Determine whether the user can view attachment statistics.
     */
    public function viewStatistics(User $user): bool
    {
        // Only administrators and healthcare managers can view statistics
        return $user->hasRole('administrator') || 
               $user->hasRole('healthcare_manager');
    }

    /**
     * Determine whether the user can download the attachment.
     */
    public function download(User $user, MessageAttachment $messageAttachment): bool
    {
        // Users can download if they can view the attachment
        return $this->view($user, $messageAttachment);
    }

    /**
     * Determine whether the user can view PHI attachments.
     */
    public function viewPhiAttachments(User $user): bool
    {
        // Only authorized healthcare providers can view PHI attachments
        return $user->hasRole('healthcare_provider') && 
               $user->can('view-phi');
    }
}