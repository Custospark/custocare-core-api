<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientSearchResource extends JsonResource
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

        // Prefer display_name, otherwise first+last.
        $displayName = $user?->display_name
            ?? trim(($user?->first_name ?? '') . ' ' . ($user?->last_name ?? ''));

        return [
            // 1)(a) Patient number (patient_uuid)
            'id' => $this->id,
            // 1)(b) Patient number (patient_uuid)
            'patient_number' => $this->patient_uuid,

            // 2) User public ID (if needed by client)
            'global_user_uuid' => $user?->global_user_uuid,

            // 3) Display name
            'name' => $displayName !== '' ? $displayName : null,

            // 4) DOB
            'date_of_birth' => optional($this->date_of_birth)->format('Y-m-d'),

            // 5) Sex
            'biological_sex' => $this->biological_sex,

            // 6) Blood type (optional)
            'blood_type' => $this->blood_type,

            // 7) Status
            'status' => $this->status,

            // 8) Clinical flag
            'requires_isolation' => (bool) $this->requires_isolation,

            // 9) Placeholder extension point (room for more later)
            'extra' => $this->when(false, []),

            // 10) Timestamp can help sorting in UI (optional)
            'created_at' => optional($this->created_at)->toISOString(),
        ];
    }
}
