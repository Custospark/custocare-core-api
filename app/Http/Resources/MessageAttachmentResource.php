<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageAttachmentResource extends JsonResource
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
            'attachment_uuid' => $this->attachment_uuid,
            'message_id' => $this->message_id,
            'attachment_type' => $this->attachment_type,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'file_size_bytes' => $this->file_size_bytes,
            'file_size_human' => $this->formatted_file_size,
            'storage_disk' => $this->storage_disk,
            'storage_path' => $this->storage_path,
            'contains_phi' => $this->contains_phi,
            'checksum' => $this->checksum,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Relationships (loaded only when requested)
            'message' => new MessageResource($this->whenLoaded('message')),
            
            // Additional computed properties
            'is_image' => $this->isImage(),
            'is_document' => $this->isDocument(),
            'has_protected_health_info' => $this->hasProtectedHealthInfo(),
            
            // Links
            'links' => [
                'self' => route('api.message-attachments.show', $this->id),
                'download' => route('api.message-attachments.download', $this->id),
                'message' => route('api.messages.show', $this->message_id),
            ],
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Response  $response
     * @return void
     */
    public function withResponse($request, $response): void
    {
        $response->header('X-Resource-Type', 'MessageAttachment');
    }
}