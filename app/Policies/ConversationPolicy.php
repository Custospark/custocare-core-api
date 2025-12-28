<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Log;

class ConversationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): Response
    {
        // Only users with conversation viewing permission can view conversations
        if ($user->can('view conversations')) {
            return Response::allow();
        }

        Log::warning('Unauthorized attempt to view conversations', ['user_id' => $user->id]);
        return Response::deny('You do not have permission to view conversations.');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Conversation $conversation): Response
    {
        // User can view if they are a participant or have view permission
        if ($user->can('view any conversation')) {
            return Response::allow();
        }

        // Check if user is a participant
        $isParticipant = $conversation->participants()->where('user_id', $user->id)->exists();

        if ($isParticipant) {
            return Response::allow();
        }

        // Check if user belongs to the same facility
        if ($user->facility_id === $conversation->facility_id && $user->can('view facility conversations')) {
            return Response::allow();
        }

        Log::warning('Unauthorized attempt to view conversation', [
            'user_id' => $user->id,
            'conversation_id' => $conversation->id
        ]);
        
        return Response::deny('You do not have permission to view this conversation.');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): Response
    {
        // User must have permission to create conversations
        if ($user->can('create conversations')) {
            return Response::allow();
        }

        Log::warning('Unauthorized attempt to create conversation', ['user_id' => $user->id]);
        return Response::deny('You do not have permission to create conversations.');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Conversation $conversation): Response
    {
        // Admins can update any conversation
        if ($user->can('manage conversations')) {
            return Response::allow();
        }

        // Creator can update their own conversations
        if ($conversation->created_by_user_id === $user->id) {
            return Response::allow();
        }

        // Participants with admin role can update
        $isAdmin = $conversation->participants()
            ->where('user_id', $user->id)
            ->where('is_admin', true)
            ->exists();

        if ($isAdmin) {
            return Response::allow();
        }

        // Facility admins can update conversations in their facility
        if ($user->facility_id === $conversation->facility_id && $user->can('manage facility conversations')) {
            return Response::allow();
        }

        Log::warning('Unauthorized attempt to update conversation', [
            'user_id' => $user->id,
            'conversation_id' => $conversation->id
        ]);
        
        return Response::deny('You do not have permission to update this conversation.');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Conversation $conversation): Response
    {
        // Only admins and system users can delete conversations
        if ($user->can('delete conversations')) {
            // Check if conversation can be deleted (not locked and no messages)
            if ($conversation->isLocked()) {
                return Response::deny('Cannot delete a locked conversation.');
            }

            if ($conversation->messages()->count() > 0) {
                return Response::deny('Cannot delete a conversation with messages.');
            }

            return Response::allow();
        }

        Log::warning('Unauthorized attempt to delete conversation', [
            'user_id' => $user->id,
            'conversation_id' => $conversation->id
        ]);
        
        return Response::deny('You do not have permission to delete conversations.');
    }

    /**
     * Determine whether the user can archive the model.
     */
    public function archive(User $user, Conversation $conversation): Response
    {
        // Admins and participants with admin role can archive
        if ($user->can('manage conversations')) {
            return Response::allow();
        }

        // Creator can archive their own conversations
        if ($conversation->created_by_user_id === $user->id) {
            return Response::allow();
        }

        // Participants with admin role can archive
        $isAdmin = $conversation->participants()
            ->where('user_id', $user->id)
            ->where('is_admin', true)
            ->exists();

        if ($isAdmin) {
            return Response::allow();
        }

        // Facility admins can archive conversations in their facility
        if ($user->facility_id === $conversation->facility_id && $user->can('manage facility conversations')) {
            return Response::allow();
        }

        Log::warning('Unauthorized attempt to archive conversation', [
            'user_id' => $user->id,
            'conversation_id' => $conversation->id
        ]);
        
        return Response::deny('You do not have permission to archive this conversation.');
    }

    /**
     * Determine whether the user can lock the model.
     */
    public function lock(User $user, Conversation $conversation): Response
    {
        // Only admins and facility admins can lock conversations
        if ($user->can('manage conversations')) {
            return Response::allow();
        }

        // Facility admins can lock conversations in their facility
        if ($user->facility_id === $conversation->facility_id && $user->can('manage facility conversations')) {
            return Response::allow();
        }

        Log::warning('Unauthorized attempt to lock conversation', [
            'user_id' => $user->id,
            'conversation_id' => $conversation->id
        ]);
        
        return Response::deny('You do not have permission to lock this conversation.');
    }

    /**
     * Determine whether the user can activate the model.
     */
    public function activate(User $user, Conversation $conversation): Response
    {
        // Same permissions as lock
        return $this->lock($user, $conversation);
    }

    /**
     * Determine whether the user can mark the conversation as emergency.
     */
    public function markEmergency(User $user, Conversation $conversation): Response
    {
        // Only admins, facility admins, and participants with admin role can mark as emergency
        if ($user->can('manage conversations')) {
            return Response::allow();
        }

        // Participants with admin role can mark as emergency
        $isAdmin = $conversation->participants()
            ->where('user_id', $user->id)
            ->where('is_admin', true)
            ->exists();

        if ($isAdmin) {
            return Response::allow();
        }

        // Facility admins can mark conversations as emergency in their facility
        if ($user->facility_id === $conversation->facility_id && $user->can('manage facility conversations')) {
            return Response::allow();
        }

        Log::warning('Unauthorized attempt to mark conversation as emergency', [
            'user_id' => $user->id,
            'conversation_id' => $conversation->id
        ]);
        
        return Response::deny('You do not have permission to mark this conversation as emergency.');
    }

    /**
     * Determine whether the user can update PHI status.
     */
    public function updatePHI(User $user, Conversation $conversation): Response
    {
        // Only admins and privacy officers can update PHI status
        if ($user->can('manage phi')) {
            return Response::allow();
        }

        // Privacy officers in the same facility
        if ($user->facility_id === $conversation->facility_id && $user->can('manage facility phi')) {
            return Response::allow();
        }

        Log::warning('Unauthorized attempt to update PHI status', [
            'user_id' => $user->id,
            'conversation_id' => $conversation->id
        ]);
        
        return Response::deny('You do not have permission to update PHI status of this conversation.');
    }

    /**
     * Determine whether the user can view participants.
     */
    public function viewParticipants(User $user, Conversation $conversation): Response
    {
        // User can view participants if they can view the conversation
        return $this->view($user, $conversation);
    }

    /**
     * Determine whether the user can add participants.
     */
    public function addParticipant(User $user, Conversation $conversation): Response
    {
        // Only admins, creator, and participants with admin role can add participants
        if ($user->can('manage conversations')) {
            return Response::allow();
        }

        // Creator can add participants to their own conversations
        if ($conversation->created_by_user_id === $user->id) {
            return Response::allow();
        }

        // Participants with admin role can add participants
        $isAdmin = $conversation->participants()
            ->where('user_id', $user->id)
            ->where('is_admin', true)
            ->exists();

        if ($isAdmin) {
            return Response::allow();
        }

        // Facility admins can add participants to conversations in their facility
        if ($user->facility_id === $conversation->facility_id && $user->can('manage facility conversations')) {
            return Response::allow();
        }

        Log::warning('Unauthorized attempt to add participant to conversation', [
            'user_id' => $user->id,
            'conversation_id' => $conversation->id
        ]);
        
        return Response::deny('You do not have permission to add participants to this conversation.');
    }

    /**
     * Determine whether the user can remove participants.
     */
    public function removeParticipant(User $user, Conversation $conversation): Response
    {
        // Same permissions as addParticipant
        return $this->addParticipant($user, $conversation);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Conversation $conversation): Response
    {
        // Only admins can restore soft-deleted conversations
        if ($user->can('restore conversations')) {
            return Response::allow();
        }

        Log::warning('Unauthorized attempt to restore conversation', [
            'user_id' => $user->id,
            'conversation_id' => $conversation->id
        ]);
        
        return Response::deny('You do not have permission to restore conversations.');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Conversation $conversation): Response
    {
        // Only super admins can force delete
        if ($user->can('force delete conversations')) {
            return Response::allow();
        }

        Log::warning('Unauthorized attempt to force delete conversation', [
            'user_id' => $user->id,
            'conversation_id' => $conversation->id
        ]);
        
        return Response::deny('You do not have permission to permanently delete conversations.');
    }

    /**
     * Determine whether the user can manage conversations.
     */
    public function manage(User $user): Response
    {
        // Super admins and conversation managers
        if ($user->can('manage conversations')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to manage conversations.');
    }
}