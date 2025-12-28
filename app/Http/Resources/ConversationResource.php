<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = Auth::user();
        $isAdmin = $user && $user->can('manage', $this->resource);
        
        return [
            'id' => $this->id,
            'conversation_uuid' => $this->conversation_uuid,
            'facility_id' => $this->facility_id,
            'conversation_type' => $this->conversation_type,
            'visit_id' => $this->visit_id,
            'appointment_id' => $this->appointment_id,
            'department_code' => $this->department_code,
            'title' => $this->title,
            'contains_phi' => $this->contains_phi,
            'is_emergency' => $this->is_emergency,
            'status' => $this->status,
            'created_by_user_id' => $this->created_by_user_id,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Include related data conditionally
            'facility' => $this->whenLoaded('facility', fn() => new FacilityResource($this->facility)),
            'visit' => $this->whenLoaded('visit', fn() => new VisitResource($this->visit)),
            'appointment' => $this->whenLoaded('appointment', fn() => new AppointmentResource($this->appointment)),
            'created_by' => $this->whenLoaded('createdBy', fn() => new UserResource($this->createdBy)),
            
            // Only include participants for group and direct conversations, and if user is authorized
            'participants' => $this->when(
                $this->shouldIncludeParticipants($request, $isAdmin),
                fn() => UserResource::collection($this->participants)
            ),
            
            // Include message count for conversations the user participates in
            'message_count' => $this->when(
                $user && $this->participants()->where('user_id', $user->id)->exists(),
                fn() => $this->messages()->count()
            ),
            
            // Include metadata
            'metadata' => [
                'is_active' => $this->isActive(),
                'is_archived' => $this->isArchived(),
                'is_locked' => $this->isLocked(),
                'has_phi' => $this->hasPHI(),
            ],
            
            // Include links
            'links' => [
                'self' => route('api.conversations.show', $this->conversation_uuid),
                'messages' => route('api.conversations.messages.index', $this->conversation_uuid),
                'participants' => route('api.conversations.participants.index', $this->conversation_uuid),
            ],
        ];
    }
    
    /**
     * Determine if participants should be included in the response.
     */
    private function shouldIncludeParticipants(Request $request, bool $isAdmin): bool
    {
        $user = Auth::user();
        
        // Always include for admins
        if ($isAdmin) {
            return true;
        }
        
        // Include if explicitly requested
        if ($request->has('include') && in_array('participants', explode(',', $request->input('include')))) {
            return true;
        }
        
        // Include if user is a participant in the conversation
        if ($user && $this->participants()->where('user_id', $user->id)->exists()) {
            return true;
        }
        
        // Don't include by default for privacy
        return false;
    }
    
    /**
     * Customize the response for the resource.
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Conversation retrieved successfully',
        ];
    }
}