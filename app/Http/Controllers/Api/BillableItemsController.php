<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\ServiceCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BillableItemsController extends Controller
{
    /**
     * Get all billable items and services for a facility.
     * 
     * Retrieves item_code, item_name, unit_cost, package_quantity from inventory items
     * and service_code, service_name, status, price_amount from service catalog.
     * Combines them into a unified response with stock/quantity information.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getBillableItemsAndServices(Request $request): JsonResponse
    {
        try {
            // 1) Get facility ID from header
            $facilityId = (int) $request->header('X-Facility-Id');
            
            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing X-Facility-Id header.',
                    'errors' => ['facility_id' => ['X-Facility-Id header is required.']],
                    'data' => [],
                ], 422);
            }

            // 2) Optional filters
            $filters = $request->validate([
                'category' => 'nullable|string|max:100',
                'search' => 'nullable|string|max:100',
                'limit' => 'nullable|integer|min:1|max:500',
                'include_inactive' => 'nullable|boolean',
                'type' => 'nullable|in:inventory,service,all',
            ]);

            $limit = $filters['limit'] ?? 500;
            $includeInactive = $filters['include_inactive'] ?? false;
            $type = $filters['type'] ?? 'all';
            $searchTerm = $filters['search'] ?? null;
            $category = $filters['category'] ?? null;

            // 3) Initialize collection for combined results
            $billableItems = collect();

            // 4) Get inventory items (medications, supplies, etc.) - ONLY is_billable = true
            if ($type === 'all' || $type === 'inventory') {
                $inventoryQuery = InventoryItem::query()
                    ->where('facility_id', $facilityId)
                    ->where('is_billable', true); // CRITICAL: Only billable items

                // Filter by status
                if (!$includeInactive) {
                    $inventoryQuery->where('status', 'active');
                }

                // Filter by category if provided
                if ($category) {
                    $inventoryQuery->where('item_category', $category);
                }

                // Search functionality
                if ($searchTerm) {
                    $inventoryQuery->where(function ($q) use ($searchTerm) {
                        $q->where('item_code', 'LIKE', "%{$searchTerm}%")
                            ->orWhere('item_name', 'LIKE', "%{$searchTerm}%")
                            ->orWhere('generic_name', 'LIKE', "%{$searchTerm}%")
                            ->orWhere('brand_name', 'LIKE', "%{$searchTerm}%");
                    });
                }

                // Get inventory items with current stock levels using a subquery
                $inventoryItems = $inventoryQuery
                    ->select([
                        'id',
                        'item_uuid',
                        'item_code',
                        'item_name',
                        'item_category',
                        'item_subcategory',
                        'generic_name',
                        'brand_name',
                        'dosage_form',
                        'strength',
                        'unit_of_measure',
                        'package_quantity',
                        'unit_cost',
                        'currency_code',
                        'requires_prescription',
                        'status',
                    ])
                    ->limit($limit)
                    ->get();

                // Transform inventory items to match the frontend ServiceItem interface
                $transformedInventory = $inventoryItems->map(function ($item) {
                    // Calculate available quantity based on packaging
                    $availablePackages = (int) ($item->total_stock ?? 0);
                    $unitsPerPackage = $item->package_quantity ?? 1;
                    $availableUnits = $availablePackages * $unitsPerPackage;

                    return [
                        // Core identifiers - matches ServiceItem interface
                        'id' => $item->id,
                        'code' => $item->item_code,
                        'name' => $item->item_name,
                        'unitPrice' => (float) $item->unit_cost,
                        'category' => $item->item_category,
                        
                        // Extended fields for inventory items
                        '_type' => 'inventory',
                        '_uuid' => $item->item_uuid,
                        'description' => $this->formatItemDescription($item),
                        'subcategory' => $item->item_subcategory,
                        'generic_name' => $item->generic_name,
                        'brand_name' => $item->brand_name,
                        'dosage_form' => $item->dosage_form,
                        'strength' => $item->strength,
                        'unit_of_measure' => $item->unit_of_measure,
                        'package_quantity' => $unitsPerPackage,
                        'requires_prescription' => (bool) $item->requires_prescription,
                        'currency' => $item->currency_code ?? 'UGX',
                        
                        // Stock information
                        'stock' => [
                            'available_packages' => $availablePackages,
                            'available_units' => $availableUnits,
                            'units_per_package' => $unitsPerPackage,
                            'has_stock' => $availablePackages > 0,
                            'is_low_stock' => $availablePackages < ($item->reorder_point ?? 5),
                        ],
                        
                        // Status
                        'status' => $item->status,
                        'is_active' => $item->status === 'active',
                    ];
                });

                $billableItems = $billableItems->concat($transformedInventory);
            }

            // 5) Get service catalog items
            if ($type === 'all' || $type === 'service') {
                $serviceQuery = ServiceCatalog::query()
                    ->where('facility_id', $facilityId);

                // Filter by category if provided
                if ($category) {
                    $serviceQuery->where('service_category', $category);
                }

                // Search functionality
                if ($searchTerm) {
                    $serviceQuery->where(function ($q) use ($searchTerm) {
                        $q->where('service_code', 'LIKE', "%{$searchTerm}%")
                            ->orWhere('service_name', 'LIKE', "%{$searchTerm}%");
                    });
                }

                // Get service items
                $serviceItems = $serviceQuery
                    ->select([
                        'id',
                        'service_uuid',
                        'service_code',
                        'service_name',
                        'service_category',
                        'price_amount',
                        'currency_code',
                        'code_system',
                        'default_duration_minutes',
                        'risk_level',
                        'requires_informed_consent',
                        'status',
                    ])
                    ->limit($limit)
                    ->get();

                // Transform service items to match the frontend ServiceItem interface
                $transformedServices = $serviceItems->map(function ($service) {
                    return [
                        // Core identifiers - matches ServiceItem interface
                        'id' => $service->id,
                        'code' => $service->service_code,
                        'name' => $service->service_name,
                        'unitPrice' => (float) $service->price_amount,
                        'category' => $service->service_category,
                        
                        // Extended fields for service items
                        '_type' => 'service',
                        '_uuid' => $service->service_uuid,
                        'currency' => $service->currency_code ?? 'UGX',
                        'code_system' => $service->code_system,
                        'default_duration_minutes' => $service->default_duration_minutes,
                        'risk_level' => $service->risk_level,
                        'requires_consent' => (bool) $service->requires_informed_consent,
                        
                        // Services are always "in stock"
                        'stock' => [
                            'has_stock' => true,
                            'available' => 'unlimited',
                        ],
                        
                        // Status
                        'status' => $service->status,
                        'is_active' => $service->status === 'active',
                    ];
                });

                $billableItems = $billableItems->concat($transformedServices);
            }

            // 6) Sort results - active items first, then by category and name
            $sortedItems = $billableItems->sortBy([
                ['is_active', 'desc'],
                ['category', 'asc'],
                ['name', 'asc'],
            ])->values();

            // 7) Group by category for frontend consumption (matches MOCK_SERVICES structure)
            $groupedByCategory = $sortedItems->groupBy('category')->map(function ($items, $category) {
                return [
                    'category' => $category,
                    'items' => $items->map(function ($item) {
                        // Return only the fields that match ServiceItem interface
                        // for direct compatibility with existing frontend code
                        return [
                            'id' => $item['id'],
                            'code' => $item['code'],
                            'name' => $item['name'],
                            'unitPrice' => $item['unitPrice'],
                            'category' => $item['category'],
                        ];
                    })->values(),
                    'count' => $items->count(),
                ];
            })->values();

            // 8) Prepare summary statistics
            $summary = [
                'total_items' => $sortedItems->count(),
                'total_inventory' => $sortedItems->where('_type', 'inventory')->count(),
                'total_services' => $sortedItems->where('_type', 'service')->count(),
                'categories' => $sortedItems->pluck('category')->unique()->values(),
                'total_value' => $sortedItems->sum('unitPrice'),
                'average_price' => $sortedItems->count() > 0 
                    ? round($sortedItems->avg('unitPrice'), 2) 
                    : 0,
            ];

            // 9) Return response in a format that easily replaces MOCK_SERVICES
            return response()->json([
                'success' => true,
                'message' => 'Billable items and services retrieved successfully.',
                'data' => [
                    // Direct replacement for MOCK_SERVICES - matches ServiceItem[] interface
                    'services' => $sortedItems->map(function ($item) {
                        return [
                            'id' => $item['id'],
                            'code' => $item['code'],
                            'name' => $item['name'],
                            'unitPrice' => $item['unitPrice'],
                            'category' => $item['category'],
                        ];
                    })->values(),
                    
                    // Full data with all fields including stock information
                    'items_full' => $sortedItems,
                    
                    // Grouped by category for easier UI rendering
                    'grouped_by_category' => $groupedByCategory,
                    
                    // Low stock items (inventory only)
                    'low_stock_items' => $sortedItems
                        ->where('_type', 'inventory')
                        ->where('stock.has_stock', false)
                        ->take(10)
                        ->values(),
                ],
                'summary' => $summary,
                'meta' => [
                    'facility_id' => $facilityId,
                    'filters_applied' => $filters,
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
                'data' => [],
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve billable items and services', [
                'facility_id' => $facilityId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving billable items.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => [],
            ], 500);
        }
    }

    /**
     * Format item description for display.
     *
     * @param InventoryItem $item
     * @return string
     */
    private function formatItemDescription(InventoryItem $item): string
    {
        $parts = [];

        if ($item->generic_name) {
            $parts[] = $item->generic_name;
        }

        if ($item->strength) {
            $parts[] = $item->strength;
        }

        if ($item->dosage_form) {
            $parts[] = $item->dosage_form;
        }

        if ($item->brand_name && $item->brand_name !== $item->generic_name) {
            $parts[] = "({$item->brand_name})";
        }

        return implode(' ', $parts);
    }
}