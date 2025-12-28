<?php

namespace App\Policies;

use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConversationParticipantPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any conversation participants.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_conversation_participants');
    }

    /**
     * Determine whether the user can view the conversation participant.
     *
     * @param User $user
     * @param ConversationParticipant $participant
     * @return bool
     */
    public function view(User $user, ConversationParticipant $participant): bool
    {
        // User can view if they are the participant or have permission
        return $user->isParticipant($participant) || 
               $user->hasPermission('view_conversation_participants');
    }

    /**
     * Determine whether the user can create conversation participants.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_conversation_participants');
    }

    /**
     * Determine whether the user can update the conversation participant.
     *
     * @param User $user
     * @param ConversationParticipant $participant
     * @return bool
     */
    public function update(User $user, ConversationParticipant $participant): bool
    {
        // User can update if they are the participant or have permission
        return $user->isParticipant($participant) || 
               $user->hasPermission('update_conversation_participants');
    }

    /**
     * Determine whether the user can delete the conversation participant.
     *
     * @param User $user
     * @param ConversationParticipant $participant
     * @return bool
     */
    public function delete(User $user, ConversationParticipant $participant): bool
    {
        // Cannot delete conversation owner
        if ($participant->role === ConversationParticipant::ROLE_OWNER) {
            return false;
        }

        return $user->hasPermission('delete_conversation_participants');
    }

    /**
     * Determine whether the user can mute the conversation participant.
     *
     * @param User $user
     * @param ConversationParticipant $participant
     * @return bool
     */
    public function mute(User $user, ConversationParticipant $participant): bool
    {
        // Only moderators and owners can mute participants
        if ($participant->isPrivileged()) {
            // Cannot mute other privileged users unless you're the owner
            if ($participant->role === ConversationParticipant::ROLE_OWNER) {
                return false;
            }
            
            return $user->hasPermission('manage_conversation_participants');
        }

        return $user->hasPermission('manage_conversation_participants') ||
               $user->isConversationModerator($participant->conversation_id);
    }

    /**
     * Determine whether the user can change participant role.
     *
     * @param User $user
     * @param ConversationParticipant $participant
     * @return bool
     */
    public function changeRole(User $user, ConversationParticipant $participant): bool
    {
        // Only conversation owners can change roles
        return $user->isConversationOwner($participant->conversation_id);
    }

    /**
     * Determine whether the user can leave the conversation.
     *
     * @param User $user
     * @param ConversationParticipant $participant
     * @return bool
     */
    public function leave(User $user, ConversationParticipant $participant): bool
    {
        // User can leave if they are the participant
        // Conversation owner cannot leave without transferring ownership
        if ($participant->role === ConversationParticipant::ROLE_OWNER) {
            return false;
        }

        return $user->isParticipant($participant);
    }
}