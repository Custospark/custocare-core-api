<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'global_user_uuid' => $this->global_user_uuid,
            'national_id_country_code' => $this->national_id_country_code,
            'identity_state' => $this->identity_state,
            'identity_state_label' => $this->getIdentityStates()[$this->identity_state] ?? $this->identity_state,
            'identity_verified_at' => $this->identity_verified_at?->toIso8601String(),
            'identity_verification_method' => $this->identity_verification_method,
            'identity_verified_by_staff_id' => $this->identity_verified_by_staff_id,
            'data_residency_region' => $this->data_residency_region,
            'allowed_processing_regions' => $this->allowed_processing_regions,
            'created_from_facility_id' => $this->created_from_facility_id,
            'email_hash' => $this->when($request->user()->can('viewSensitiveData', $this->resource), $this->email_hash),
            'phone_hash' => $this->when($request->user()->can('viewSensitiveData', $this->resource), $this->phone_hash),
            'requires_password_change' => $this->requires_password_change,
            'mfa_enabled' => $this->mfa_enabled,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'last_login_ip' => $this->when($request->user()->can('viewSensitiveData', $this->resource), $this->last_login_ip),
            'failed_login_attempts' => $this->failed_login_attempts,
            'account_locked_until' => $this->account_locked_until?->toIso8601String(),
            'is_account_locked' => $this->isAccountLocked(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'created_by_staff_id' => $this->created_by_staff_id,
            'updated_by_staff_id' => $this->updated_by_staff_id,
            'created_ip' => $this->when($request->user()->can('viewSensitiveData', $this->resource), $this->created_ip),
            'metadata' => $this->metadata,
            'links' => [
                'self' => route('api.users.show', $this->global_user_uuid),
                'verify' => route('api.users.verify', $this->id),
                'suspend' => route('api.users.suspend', $this->id),
                'restore' => route('api.users.restore', $this->id),
                'archive' => route('api.users.archive', $this->id),
            ],
        ];
    }
}