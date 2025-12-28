<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ServiceVersionResource
 * 
 * Transforms ServiceVersion model into JSON API response.
 * Includes related resources for service catalog, facility, and created by staff.
 */
class ServiceVersionResource extends JsonResource
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
            'version_uuid' => $this->version_uuid,
            
            // Related entities
            'service_catalog' => $this->whenLoaded('serviceCatalog', function () {
                return new ServiceCatalogResource($this->serviceCatalog);
            }),
            'service_catalog_id' => $this->service_catalog_id,
            
            'facility' => $this->whenLoaded('facility', function () {
                return new FacilityResource($this->facility);
            }),
            'facility_id' => $this->facility_id,
            
            // Version control
            'version_number' => $this->version_number,
            'valid_from' => $this->valid_from,
            'valid_to' => $this->valid_to,
            'is_current' => $this->is_current,
            'is_currently_valid' => $this->isCurrentlyValid(),
            
            // Pricing information
            'currency_code' => $this->currency_code,
            'base_price_amount' => (float) $this->base_price_amount,
            'facility_markup_percentage' => $this->facility_markup_percentage !== null 
                ? (float) $this->facility_markup_percentage 
                : null,
            'final_price_amount' => (float) $this->final_price_amount,
            'display_price' => $this->display_price,
            
            // Insurance & coverage
            'insurance_coverage_rates' => $this->insurance_coverage_rates,
            'insurance_coverage_summary' => $this->insurance_coverage_summary,
            'requires_preauthorization' => $this->requires_preauthorization,
            'preauthorization_criteria' => $this->preauthorization_criteria,
            'preauth_processing_days' => $this->preauth_processing_days,
            
            // Billing rules
            'is_billable' => $this->is_billable,
            'billing_method' => $this->billing_method,
            'minimum_billable_units' => (float) $this->minimum_billable_units,
            'maximum_billable_units' => $this->maximum_billable_units !== null 
                ? (float) $this->maximum_billable_units 
                : null,
            'bundled_service_ids' => $this->bundled_service_ids,
            
            // Modifiers
            'allowed_modifiers' => $this->allowed_modifiers,
            'modifier_price_adjustments' => $this->modifier_price_adjustments,
            
            // Documentation requirements
            'documentation_requirements' => $this->documentation_requirements,
            'medical_necessity_criteria' => $this->medical_necessity_criteria,
            'required_diagnosis_codes' => $this->required_diagnosis_codes,
            
            // Cost accounting
            'direct_cost' => $this->direct_cost !== null ? (float) $this->direct_cost : null,
            'indirect_cost' => $this->indirect_cost !== null ? (float) $this->indirect_cost : null,
            'target_margin_percentage' => $this->target_margin_percentage !== null 
                ? (float) $this->target_margin_percentage 
                : null,
            
            // Audit & snapshot
            'version_snapshot' => $this->version_snapshot,
            'version_hash' => $this->when($request->user() && $request->user()->can('viewVersionHash', $this), 
                $this->version_hash),
            'change_notes' => $this->change_notes,
            
            // Created by
            'created_by' => $this->whenLoaded('createdBy', function () {
                return new StaffResource($this->createdBy);
            }),
            'created_by_staff_id' => $this->created_by_staff_id,
            
            // Metadata
            'metadata' => $this->metadata,
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Links
            'links' => [
                'self' => route('service-versions.show', $this->version_uuid),
                'service_catalog' => $this->service_catalog_id 
                    ? route('service-catalogs.show', $this->service_catalog_id) 
                    : null,
                'facility' => $this->facility_id 
                    ? route('facilities.show', $this->facility_id) 
                    : null,
            ]
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Service version retrieved successfully',
            'status' => 200,
            'meta' => [
                'version' => '1.0',
                'api_version' => config('app.api_version', '1.0'),
                'copyright' => '© ' . date('Y') . ' Healthcare System. All rights reserved.',
            ]
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
        $response->header('X-API-Version', config('app.api_version', '1.0'));
        $response->header('X-API-Resource', 'ServiceVersion');
    }
}