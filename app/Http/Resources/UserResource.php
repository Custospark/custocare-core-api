<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

/**
 * @property int $id
 * @property string $global_user_uuid
 * @property string $national_id_country_code
 * @property string $identity_state
 * @property string|null $identity_verified_at
 * @property string|null $identity_verification_method
 * @property string $data_residency_region
 * @property array|null $allowed_processing_regions
 * @property int|null $created_from_facility_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $title
 * @property string|null $display_name
 * @property string|null $dob
 * @property string|null $gender
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $country
 * @property string|null $postal_code
 * @property string|null $email_encrypted
 * @property string|null $phone_encrypted
 * @property bool $requires_password_change
 * @property bool $mfa_enabled
 * @property string|null $last_login_at
 * @property int $failed_login_attempts
 * @property string|null $account_locked_until
 * @property array|null $metadata
 * @property string $created_at
 * @property string $updated_at
 */
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
            'uuid' => $this->global_user_uuid,
            'national_id_country_code' => $this->national_id_country_code,
            'identity' => [
                'state' => $this->identity_state,
                'verified_at' => $this->identity_verified_at,
                'verification_method' => $this->identity_verification_method,
            ],
            'compliance' => [
                'data_residency_region' => $this->data_residency_region,
                'allowed_processing_regions' => $this->allowed_processing_regions,
                'created_from_facility_id' => $this->created_from_facility_id,
            ],
            'profile' => [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'full_name' => $this->display_name ?? trim($this->first_name . ' ' . $this->last_name),
                'title' => $this->title,
                'display_name' => $this->display_name,
                'dob' => $this->dob,
                'gender' => $this->gender,
            ],
            'contact' => [
                'email' => $this->when($this->email_encrypted, $this->decryptField('email_encrypted')),
                'phone' => $this->when($this->phone_encrypted, $this->decryptField('phone_encrypted')),
            ],
            'address' => [
                'line1' => $this->address_line1,
                'line2' => $this->address_line2,
                'city' => $this->city,
                'state' => $this->state,
                'country' => $this->country,
                'postal_code' => $this->postal_code,
            ],
            'security' => [
                'requires_password_change' => $this->requires_password_change,
                'mfa_enabled' => $this->mfa_enabled,
                'failed_login_attempts' => $this->failed_login_attempts,
                'account_locked_until' => $this->account_locked_until,
            ],
            'activity' => [
                'last_login_at' => $this->last_login_at,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ],
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Decrypt a field value safely.
     *
     * @param string $field
     * @return string|null
     */
    private function decryptField(string $field): ?string
    {
        if (empty($this->{$field})) {
            return null;
        }

        try {
            return decrypt($this->{$field});
        } catch (\Exception $e) {
            Log::error('Failed to decrypt field', [
                'user_id' => $this->id,
                'field' => $field,
                'error' => $e->getMessage()
            ]);
            
            // Return null or a masked value for security
            return null;
        }
    }
}