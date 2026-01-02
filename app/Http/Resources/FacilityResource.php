<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class FacilityResource
 * 
 * API Resource for Facility entity.
 * Transforms and formats Facility data for API responses.
 */
class FacilityResource extends JsonResource
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
            'facility_uuid' => $this->facility_uuid,
            'facility_code' => $this->facility_code,
            'facility_name' => $this->facility_name,
            'legal_entity_name' => $this->legal_entity_name,
            
            'facility_type' => $this->facility_type,
            'facility_tier' => $this->facility_tier,
            'bed_capacity' => $this->bed_capacity,
            'accreditations' => $this->accreditations,
            
            'address' => [
                'line1' => $this->address_line1,
                'line2' => $this->address_line2,
                'city' => $this->city,
                'state_province' => $this->state_province,
                'postal_code' => $this->postal_code,
                'country_code' => $this->country_code,
                'full_address' => $this->full_address,
                'coordinates' => $this->coordinates,
            ],
            
            'location' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'timezone' => $this->timezone,
            ],
            
            'contact' => [
                'main_phone' => $this->main_phone,
                'emergency_phone' => $this->emergency_phone,
                'fax' => $this->fax,
                'email' => $this->email,
                'website' => $this->website,
            ],
            
            'operations' => [
                'operating_hours' => $this->operating_hours,
                'emergency_services_hours' => $this->emergency_services_hours,
                'is_24_7' => $this->is_24_7,
                'operational_status' => $this->operational_status,
            ],
            
            'network' => [
                'parent_organization' => $this->whenLoaded('parentOrganization', function () {
                    return new FacilityResource($this->parentOrganization);
                }),
                'parent_organization_id' => $this->parent_organization_id,
                'affiliated_facility_ids' => $this->affiliated_facility_ids,
                'referral_network_facility_ids' => $this->referral_network_facility_ids,
                'health_system_name' => $this->health_system_name,
            ],
            
            'regulatory' => [
                'license_number' => $this->license_number,
                'license_issuing_authority' => $this->license_issuing_authority,
                'license_expiry_date' => $this->license_expiry_date,
                'regulatory_identifiers' => $this->regulatory_identifiers,
                'participates_in_medicare' => $this->participates_in_medicare,
                'participates_in_medicaid' => $this->participates_in_medicaid,
            ],
            
            'capabilities' => [
                'available_services' => $this->available_services,
                'specialty_services' => $this->specialty_services,
                'equipment_inventory_summary' => $this->equipment_inventory_summary,
                'has_emergency_department' => $this->has_emergency_department,
                'has_trauma_center' => $this->has_trauma_center,
                'trauma_center_level' => $this->trauma_center_level,
                'has_intensive_care' => $this->has_intensive_care,
                'has_neonatal_icu' => $this->has_neonatal_icu,
                'has_cardiac_cath_lab' => $this->has_cardiac_cath_lab,
            ],
            
            'data_residency' => [
                'data_residency_region' => $this->data_residency_region,
                'primary_database_shard' => $this->primary_database_shard,
                'replica_shard_locations' => $this->replica_shard_locations,
            ],
            
            'metrics' => [
                'average_wait_time_minutes' => $this->average_wait_time_minutes,
                'patient_satisfaction_score' => $this->patient_satisfaction_score,
                'monthly_patient_volume' => $this->monthly_patient_volume,
            ],
            
            'audit' => [
                'created_by' => $this->whenLoaded('createdBy', function () {
                    return new UserResource($this->createdBy);
                }),
                'updated_by' => $this->whenLoaded('updatedBy', function () {
                    return new UserResource($this->updatedBy);
                }),
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
                'deleted_at' => $this->deleted_at?->toIso8601String(),
            ],
            
            'metadata' => $this->metadata,
            
            // Links for HATEOAS
            'links' => [
                'self' => route('facilities.show', $this->facility_uuid),
                'parent_organization' => $this->parent_organization_id ? route('organizations.show', $this->parent_organization_id) : null,
            ],
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     *
     * @param Request $request
     * @param \Illuminate\Http\JsonResponse $response
     * @return void
     */
    public function withResponse($request, $response): void
    {
        $response->header('X-Facility-Resource', 'v1');
        
        // Add cache headers for reference data
        $response->header('Cache-Control', 'public, max-age=300'); // 5 minutes cache
        $response->header('X-Cache-TTL', '300');
        
        // Add pagination headers if applicable
        if (isset($this->resource->resource) && method_exists($this->resource->resource, 'currentPage')) {
            $response->header('X-Total-Count', $this->resource->resource->total());
            $response->header('X-Page-Count', $this->resource->resource->lastPage());
            $response->header('X-Current-Page', $this->resource->resource->currentPage());
        }
    }

    /**
     * Get any additional data that should be returned with the resource array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
            'meta' => [
                'api_version' => '1.0',
                'timestamp' => now()->toIso8601String(),
                'resource_type' => 'facility',
                'shard_info' => $this->data_residency_region ? [
                    'region' => $this->data_residency_region,
                    'primary_shard' => $this->primary_database_shard,
                ] : null,
            ],
        ];
    }
}