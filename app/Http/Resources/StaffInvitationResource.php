<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffInvitationResource extends JsonResource
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
            'invitation_uuid' => $this->invitation_uuid,
            'status' => $this->status,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            
            // Relationships (loaded only when needed)
            'staff' => new StaffResource($this->whenLoaded('staff')),
            'facility' => new FacilityResource($this->whenLoaded('facility')),
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'role' => new FacilityStaffRoleResource($this->whenLoaded('role')),
            'invited_by' => new StaffResource($this->whenLoaded('invitedBy')),
            
            // Metadata
            'metadata' => $this->metadata,
            
            // Computed properties
            'is_expired' => $this->isExpired(),
            'is_pending' => $this->isPending(),
            'can_be_accepted' => $this->canBeAccepted(),
            'days_until_expiry' => $this->expires_at?->diffInDays(now(), false),
            
            // Links
            // '_links' => [
            //     'self' => route('staff-invitations.show', $this->id),
            //     'accept' => $this->canBeAccepted() ? route('staff-invitations.accept', $this->id) : null,
            //     'decline' => $this->isPending() ? route('staff-invitations.decline', $this->id) : null,
            //     'resend' => $this->isPending() ? route('staff-invitations.resend', $this->id) : null,
            // ]
        ];
    }

    /**
     * Customize the response for a given request.
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Staff invitation retrieved successfully.',
            'meta' => [
                'status_meanings' => [
                    'pending' => 'Invitation has been sent but not yet responded to',
                    'accepted' => 'Invitation has been accepted by the staff member',
                    'declined' => 'Invitation has been declined by the staff member',
                    'expired' => 'Invitation has expired without a response'
                ]
            ]
        ];
    }
}