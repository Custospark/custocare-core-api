<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ServiceCatalogResource extends JsonResource
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
            'service_uuid' => $this->service_uuid,
            'service_code' => $this->service_code,
            'code_system' => $this->code_system,
            'service_name' => $this->service_name,
            'price_amount' => $this->price_amount,
            'currency_code' => $this->currency_code,
            'service_description' => $this->service_description,
            'alternate_names' => $this->alternate_names,
            'service_category' => $this->service_category,
            'service_subcategories' => $this->service_subcategories,
            'department_specialty' => $this->department_specialty,
            'regulatory_approval_status' => $this->regulatory_approval_status,
            'required_certifications' => $this->required_certifications,
            'minimum_required_credentials' => $this->minimum_required_credentials,
            'required_equipment' => $this->required_equipment,
            'required_facility_capabilities' => $this->required_facility_capabilities,
            'default_duration_minutes' => $this->default_duration_minutes,
            'typical_indications' => $this->typical_indications,
            'contraindications' => $this->contraindications,
            'prerequisites' => $this->prerequisites,
            'commonly_paired_services' => $this->commonly_paired_services,
            'risk_level' => $this->risk_level,
            'requires_informed_consent' => $this->requires_informed_consent,
            'consent_form_template' => $this->consent_form_template,
            'applicable_region' => $this->applicable_region,
            'approved_countries' => $this->approved_countries,
            'state_specific_regulations' => $this->state_specific_regulations,
            'status' => $this->status,
            'effective_from' => $this->effective_from,
            'effective_to' => $this->effective_to,
            'created_by_staff_id' => $this->created_by_staff_id,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            
            // Relationships (loaded only when needed)
            'created_by' => $this->whenLoaded('createdBy', function () {
                return new StaffResource($this->createdBy);
            }),
            
            // Computed properties
            'is_currently_effective' => $this->when(
                $this->resource->relationLoaded('effectiveStatus') || $request->has('check_effectiveness'),
                function () use ($request) {
                    $date = $request->get('effective_date', now()->toDateString());
                    return $this->isEffective($date);
                }
            ),
            
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
        $response->header('X-Service-Catalog-Version', '1.0');
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function with($request): array
    {
        return [
            'success' => true,
            'message' => 'Service catalog retrieved successfully.',
            'meta' => [
                'version' => '1.0',
                'timestamp' => now()->toISOString(),
                'copyright' => '© ' . date('Y') . ' Healthcare API. All rights reserved.'
            ]
        ];
    }
}