<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message_uuid' => $this->message_uuid,
            'conversation_id' => $this->conversation_id,
            
            // Sender information
            'sender_type' => $this->sender_type,
            'sender' => $this->whenLoaded('sender', function () {
                return [
                    'id' => $this->sender_id,
                    'type' => $this->sender_type,
                    'name' => $this->sender->name ?? null,
                    // Add other sender fields as needed
                ];
            }),
            
            // Message content
            'message_type' => $this->message_type,
            'content' => $this->when(
                $request->user() && $this->shouldShowContent($request->user()),
                function () {
                    // In a real app, you would decrypt the content here
                    return 'Message content'; // Placeholder
                }
            ),
            'content_hash' => $this->content_hash,
            
            // Clinical flags
            'contains_phi' => $this->contains_phi,
            'is_clinical' => $this->is_clinical,
            'requires_acknowledgement' => $this->requires_acknowledgement,
            
            // Threading
            'parent_message_id' => $this->parent_message_id,
            'parent_message' => new MessageResource($this->whenLoaded('parent')),
            'replies' => MessageResource::collection($this->whenLoaded('replies')),
            
            // Delivery status
            'delivery_status' => $this->delivery_status,
            
            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'edited_at' => $this->edited_at?->toISOString(),
            
            // Editor
            'editor' => new UserResource($this->whenLoaded('editor')),
            'edited_by_user_id' => $this->edited_by_user_id,
            
            // Conversation
            'conversation' => new ConversationResource($this->whenLoaded('conversation')),
            
            // Additional metadata
            'is_edited' => $this->edited_at !== null,
            'is_deleted' => $this->deleted_at !== null,
            'can_be_edited' => $this->canBeEdited(),
            'can_be_deleted' => $this->canBeDeleted(),
        ];
    }

    /**
     * Check if content should be shown to the user.
     */
    private function shouldShowContent($user): bool
    {
        // Implement authorization logic based on user role and message properties
        // For example, hide PHI for unauthorized users
        return true; // Simplified for this example
    }

    /**
     * Determine if the message can be edited.
     */
    private function canBeEdited(): bool
    {
        // Implement business logic for editability
        // For example, messages can only be edited within 5 minutes of creation
        return $this->created_at->diffInMinutes(now()) <= 5;
    }

    /**
     * Determine if the message can be deleted.
     */
    private function canBeDeleted(): bool
    {
        // Implement business logic for deletability
        // For example, system messages cannot be deleted
        return $this->sender_type !== 'system';
    }

    /**
     * Add additional metadata to the resource response.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'api_version' => 'v1',
                'copyright' => config('app.name'),
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}