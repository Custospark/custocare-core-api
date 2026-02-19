<?php

namespace App\Services\Billing\Validation;

use App\Models\InventoryItem;
use Illuminate\Support\Facades\Log;

/**
 * Billing Validation Service
 *
 * Handles validation logic for billing operations
 */
class BillingValidation
{
    /**
     * Validate inventory availability before processing billing
     * This runs OUTSIDE the transaction to fail fast without locking resources
     *
     * @param array $chargeItems Charge items from billing payload
     * @param int $staffId Staff performing the action
     * @return array Success status and validation results
     */
    public function validateInventoryAvailability(array $chargeItems, int $staffId): array
    {
        $insufficientItems = [];
        $unavailableItems = [];

        foreach ($chargeItems as $chargeItem) {
            $service = $chargeItem['service'];
            $quantity = (int) $chargeItem['quantity'];

            if ($quantity <= 0) {
                continue;
            }

            // Check if this is an inventory item
            $inventoryItem = InventoryItem::query()
                ->where('item_code', $service['code'])
                ->first();

            if (!$inventoryItem) {
                // It's a service (no inventory record) — skip validation
                continue;
            }

            // Check if inventory item is inactive
            if ($inventoryItem->status !== 'active') {
                $unavailableItems[] = [
                    'item_code' => $service['code'],
                    'item_name' => $service['name'] ?? $inventoryItem->item_name,
                    'status' => $inventoryItem->status,
                    'requested_quantity' => $quantity,
                ];

                Log::warning('Attempted to bill inactive inventory item', [
                    'item_code' => $service['code'],
                    'status' => $inventoryItem->status,
                    'requested_quantity' => $quantity,
                    'staff_id' => $staffId,
                ]);

                continue;
            }

            // Check if sufficient stock is available
            if ($inventoryItem->package_quantity < $quantity) {
                $insufficientItems[] = [
                    'item_code' => $service['code'],
                    'item_name' => $service['name'] ?? $inventoryItem->item_name,
                    'available_quantity' => $inventoryItem->package_quantity,
                    'requested_quantity' => $quantity,
                    'shortage' => $quantity - $inventoryItem->package_quantity,
                ];

                Log::warning('Insufficient inventory stock detected', [
                    'item_code' => $service['code'],
                    'available' => $inventoryItem->package_quantity,
                    'requested' => $quantity,
                    'shortage' => $quantity - $inventoryItem->package_quantity,
                    'staff_id' => $staffId,
                ]);
            }
        }

        // Return detailed error response if validation fails
        if (!empty($insufficientItems) || !empty($unavailableItems)) {
            $errorMessages = [];
            
            if (!empty($insufficientItems)) {
                $itemsList = collect($insufficientItems)->map(function ($item) {
                    return "• {$item['item_name']} (Code: {$item['item_code']}): "
                         . "Requested {$item['requested_quantity']}, Available {$item['available_quantity']}, "
                         . "Short by {$item['shortage']}";
                })->implode("\n");

                $errorMessages[] = "Insufficient stock for the following items:\n{$itemsList}";
            }

            if (!empty($unavailableItems)) {
                $itemsList = collect($unavailableItems)->map(function ($item) {
                    return "• {$item['item_name']} (Code: {$item['item_code']}): "
                         . "Status is '{$item['status']}' (Requested: {$item['requested_quantity']})";
                })->implode("\n");

                $errorMessages[] = "The following items are not available:\n{$itemsList}";
            }

            return [
                'success' => false,
                'message' => 'Cannot process billing due to inventory constraints.',
                'errors' => [
                    'inventory' => $errorMessages,
                ],
                'details' => [
                    'insufficient_items' => $insufficientItems,
                    'unavailable_items' => $unavailableItems,
                ],
            ];
        }

        Log::info('Inventory availability validation passed', [
            'total_items_checked' => count($chargeItems),
            'staff_id' => $staffId,
        ]);

        return [
            'success' => true,
            'message' => 'All inventory items are available.',
        ];
    }
}