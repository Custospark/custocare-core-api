<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataResidencyRuleResource extends JsonResource
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
            'region_code' => $this->region_code,
            'region_name' => $this->region_name,
            'data_category' => $this->data_category,
            'data_category_display' => $this->getDataCategoryDisplayName(),
            
            // Geographic restrictions
            'allowed_storage_regions' => $this->allowed_storage_regions,
            'allowed_processing_regions' => $this->allowed_processing_regions,
            'allowed_backup_regions' => $this->allowed_backup_regions,
            'prohibited_regions' => $this->prohibited_regions ?? [],
            
            // Encryption requirements
            'encryption_requirements' => $this->encryption_requirements,
            'encryption_at_rest_required' => $this->encryption_at_rest_required,
            'encryption_in_transit_required' => $this->encryption_in_transit_required,
            'encryption_in_use_required' => $this->encryption_in_use_required,
            
            // Access controls
            'cross_border_transfer_approval_required' => $this->cross_border_transfer_approval_required,
            'approval_authority' => $this->approval_authority ?? [],
            'transfer_mechanisms' => $this->transfer_mechanisms ?? [],
            
            // Retention policies
            'minimum_retention_period_years' => $this->minimum_retention_period_years,
            'maximum_retention_period_years' => $this->maximum_retention_period_years,
            'retention_basis' => $this->retention_basis,
            'retention_basis_display' => $this->getRetentionBasisDisplayName(),
            
            // Deletion requirements
            'right_to_erasure_applicable' => $this->right_to_erasure_applicable,
            'erasure_exceptions' => $this->erasure_exceptions ?? [],
            'erasure_response_time_days' => $this->erasure_response_time_days,
            
            // Breach notification
            'breach_notification_hours' => $this->breach_notification_hours,
            'notification_authorities' => $this->notification_authorities ?? [],
            
            // Applicable laws
            'applicable_regulations' => $this->applicable_regulations,
            'regulation_summary' => $this->regulation_summary,
            'legal_reference_url' => $this->legal_reference_url,
            
            // Status
            'status' => $this->status,
            'status_display' => $this->getStatusDisplayName(),
            'effective_from' => $this->effective_from?->format('Y-m-d'),
            'effective_to' => $this->effective_to?->format('Y-m-d'),
            'is_effective' => $this->isEffective(),
            
            // Audit
            'created_by_staff_id' => $this->created_by_staff_id,
            'created_by' => $this->whenLoaded('createdBy', function () {
                return new StaffResource($this->createdBy);
            }),
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Computed properties
            'display_name' => $this->display_name,
            
            // Links
            '_links' => [
                'self' => route('data-residency-rules.show', $this->id),
                'update' => route('data-residency-rules.update', $this->id),
                'delete' => route('data-residency-rules.destroy', $this->id),
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
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => '1.0',
                'resource_type' => 'data_residency_rule',
                'help' => [
                    'documentation' => route('api.documentation'),
                    'support' => route('api.support')
                ]
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
        $response->header('X-Resource-Version', '1.0');
        $response->header('X-Regulatory-Compliance', 'true');
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}