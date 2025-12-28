<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
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
            'item_uuid' => $this->item_uuid,
            'item_code' => $this->item_code,
            'item_name' => $this->item_name,
            'item_description' => $this->item_description,
            
            'item_category' => $this->item_category,
            'item_subcategory' => $this->item_subcategory,
            
            // Medication-specific fields
            'generic_name' => $this->generic_name,
            'brand_name' => $this->brand_name,
            'ndc_code' => $this->ndc_code,
            'drug_class' => $this->drug_class,
            'controlled_substance_schedule' => $this->controlled_substance_schedule,
            'active_ingredients' => $this->active_ingredients,
            'dosage_form' => $this->dosage_form,
            'strength' => $this->strength,
            'route_of_administration' => $this->route_of_administration,
            
            // Manufacturer information
            'manufacturer' => $this->manufacturer,
            'manufacturer_item_number' => $this->manufacturer_item_number,
            'supplier' => $this->supplier,
            
            // Unit information
            'unit_of_measure' => $this->unit_of_measure,
            'package_quantity' => $this->package_quantity,
            'packaging_type' => $this->packaging_type,
            
            // Pricing
            'unit_cost' => $this->unit_cost,
            'average_wholesale_price' => $this->average_wholesale_price,
            'currency_code' => $this->currency_code,
            
            // Storage & handling
            'storage_requirements' => $this->storage_requirements,
            'requires_refrigeration' => $this->requires_refrigeration,
            'requires_controlled_access' => $this->requires_controlled_access,
            'storage_location_type' => $this->storage_location_type,
            
            // Regulatory
            'requires_prescription' => $this->requires_prescription,
            'regulatory_approvals' => $this->regulatory_approvals,
            'fda_approval_number' => $this->fda_approval_number,
            
            // Safety information
            'is_hazardous' => $this->is_hazardous,
            'safety_warnings' => $this->safety_warnings,
            'contraindications' => $this->contraindications,
            'special_handling_instructions' => $this->special_handling_instructions,
            
            // Inventory management
            'is_billable' => $this->is_billable,
            'track_by_lot' => $this->track_by_lot,
            'track_by_serial' => $this->track_by_serial,
            'reorder_point' => $this->reorder_point,
            'reorder_quantity' => $this->reorder_quantity,
            'safety_stock_level' => $this->safety_stock_level,
            'max_stock_level' => $this->max_stock_level,
            
            // Status
            'status' => $this->status,
            
            // Audit
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            
            // Relationships
            'created_by' => $this->whenLoaded('createdBy', function () {
                return new StaffResource($this->createdBy);
            }),
            
            // Additional computed fields
            'is_controlled_substance' => $this->isControlledSubstance(),
            'requires_special_handling' => $this->requiresSpecialHandling(),
            
            // Links
            'links' => [
                'self' => route('api.inventory-items.show', $this->item_uuid),
                'update' => route('api.inventory-items.update', $this->item_uuid),
                'delete' => route('api.inventory-items.destroy', $this->item_uuid),
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
    }
}