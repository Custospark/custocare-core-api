<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffSearchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Keep this lean: <= 10 fields. Add more later as needed.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->whenLoaded('user');

        // Prefer display_name, fallback to first + last
        $displayName = $user?->display_name
            ?? trim(($user?->first_name ?? '') . ' ' . ($user?->last_name ?? ''));

        return [
            // 1) Staff public identifier
            'staff_number' => $this->staff_uuid,

            // 2) System user public ID (for cross-module linking)
            'global_user_uuid' => $user?->global_user_uuid,

            // 3) Display name
            'name' => $displayName !== '' ? $displayName : null,

            // 4) Professional title (e.g. Dr, RN, PharmD)
            'title' => $this->professional_title,

            // 5) Role level (drives permissions & UI behavior)
            'role' => $this->global_role_level,

            // 6) Employment status (active, suspended, etc.)
            'status' => $this->employment_status,

            // 7) Accepting new patients flag
            'accepts_new_patients' => (bool) $this->accepts_new_patients,

            // 8) Max concurrent patients (capacity hint)
            'capacity' => $this->max_concurrent_patients,

            // 9) License expiry (for quick compliance check)
            'license_expiry_date' => optional($this->license_expiry_date)->format('Y-m-d'),

            // 10) Sorting / recency support
            'created_at' => optional($this->created_at)->toISOString(),
        ];
    }
}
