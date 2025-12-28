<?php

namespace App\Http\Resources;

use App\Models\ConversationParticipant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationParticipantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ConversationParticipant $participant */
        $participant = $this->resource;

        return [
            'id' => $participant->id,
            'conversation_id' => $participant->conversation_id,
            'participant_type' => $participant->participant_type,
            'participant_id' => $participant->participant_id,
            'role' => $participant->role,
            'joined_at' => $participant->joined_at?->toISOString(),
            'left_at' => $participant->left_at?->toISOString(),
            'is_muted' => $participant->is_muted,
            'is_active' => $participant->isActive(),
            'has_left' => $participant->hasLeft(),
            'created_at' => $participant->created_at->toISOString(),
            'updated_at' => $participant->updated_at->toISOString(),
            
            // Relationships (loaded when needed)
            'conversation' => new ConversationResource($participant->whenLoaded('conversation')),
            'participant' => $this->whenLoaded('participant', function () use ($participant) {
                return $participant->participant_type === ConversationParticipant::PARTICIPANT_STAFF
                    ? new StaffResource($participant->participant)
                    : new PatientResource($participant->participant);
            }),
            
            // Links
            'links' => [
                'self' => route('conversation-participants.show', $participant->id),
                'conversation' => route('conversations.show', $participant->conversation_id),
            ],
        ];
    }
}