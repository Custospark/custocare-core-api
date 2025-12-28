<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MessageReceiptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'message_id' => $this->message_id,
            'recipient_type' => $this->recipient_type,
            'recipient_id' => $this->recipient_id,
            
            // Status timestamps
            'delivered_at' => $this->delivered_at?->toISOString(),
            'read_at' => $this->read_at?->toISOString(),
            'acknowledged_at' => $this->acknowledged_at?->toISOString(),
            
            // Status flags for convenience
            'is_delivered' => $this->isDelivered(),
            'is_read' => $this->isRead(),
            'is_acknowledged' => $this->isAcknowledged(),
            
            // Related resources (only included when loaded)
            'message' => new MessageResource($this->whenLoaded('message')),
            'recipient' => $this->whenLoaded('recipient', function () {
                // Dynamically format recipient based on type
                return [
                    'type' => $this->recipient_type,
                    'id' => $this->recipient_id,
                    'data' => $this->recipient
                ];
            }),
            
            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Links for HATEOAS
            'links' => [
                'self' => route('message-receipts.show', $this->id),
                'message' => route('messages.show', $this->message_id),
                'mark_as_delivered' => route('message-receipts.mark-as-delivered', $this->id),
                'mark_as_read' => route('message-receipts.mark-as-read', $this->id),
                'mark_as_acknowledged' => route('message-receipts.mark-as-acknowledged', $this->id),
            ]
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
        $response->header('Content-Type', 'application/json');
        $response->header('X-Resource-Type', 'message-receipt');
    }

    /**
     * Get any additional data that should be returned with the resource array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function with($request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'timestamp' => now()->toISOString(),
                'status_codes' => [
                    'delivered' => !is_null($this->delivered_at),
                    'read' => !is_null($this->read_at),
                    'acknowledged' => !is_null($this->acknowledged_at),
                ]
            ],
            'links' => [
                'documentation' => url('/api/docs/message-receipts'),
                'related_resources' => [
                    'messages' => route('messages.index'),
                ]
            ]
        ];
    }
}