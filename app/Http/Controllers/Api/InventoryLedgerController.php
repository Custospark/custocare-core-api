<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryLedger\StoreInventoryLedgerRequest;
use App\Http\Requests\InventoryLedger\UpdateInventoryLedgerRequest;
use App\Http\Resources\InventoryLedgerResource;
use App\Services\Contracts\InventoryLedgerServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

/**
 * API Controller for Inventory Ledger operations.
 * Thin controller that delegates business logic to services.
 */
class InventoryLedgerController extends Controller
{
    /**
     * The inventory ledger service instance.
     *
     * @var InventoryLedgerServiceInterface
     */
    protected InventoryLedgerServiceInterface $inventoryLedgerService;

    /**
     * Create a new controller instance.
     *
     * @param InventoryLedgerServiceInterface $inventoryLedgerService
     */
    public function __construct(InventoryLedgerServiceInterface $inventoryLedgerService)
    {
        $this->inventoryLedgerService = $inventoryLedgerService;
    }

    /**
     * Display a listing of inventory ledger entries.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        try {
            $filters = $request->only([
                'facility_id',
                'inventory_item_id',
                'transaction_type',
                'transaction_cause',
                'lot_number',
                'start_date',
                'end_date',
                'verified_only',
            ]);
            
            $perPage = $request->get('per_page', 20);
            $with = $this->getRelationships($request);
            
            $ledgerEntries = $this->inventoryLedgerService->getAllLedgerEntries($filters, $with, $perPage);
            
            return InventoryLedgerResource::collection($ledgerEntries);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve inventory ledger entries', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Store a newly created inventory ledger entry.
     *
     * @param StoreInventoryLedgerRequest $request
     * @return JsonResponse
     */
    public function store(StoreInventoryLedgerRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $ledgerEntry = $this->inventoryLedgerService->createLedgerEntry($validatedData);
            
            return response()->json([
                'success' => true,
                'message' => 'Inventory ledger entry created successfully.',
                'data' => new InventoryLedgerResource($ledgerEntry->load($this->getRelationships($request))),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to create inventory ledger entry', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create inventory ledger entry. Please try again.',
            ], 500);
        }
    }

    /**
     * Display the specified inventory ledger entry.
     *
     * @param Request $request
     * @param int $id
     * @return InventoryLedgerResource
     */
    public function show(Request $request, int $id): InventoryLedgerResource
    {
        try {
            $with = $this->getRelationships($request);
            $ledgerEntry = $this->inventoryLedgerService->getLedgerEntryById($id, $with);
            
            return new InventoryLedgerResource($ledgerEntry);
        } catch (\RuntimeException $e) {
            abort(404, $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Failed to retrieve inventory ledger entry', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);
            
            abort(500, 'Failed to retrieve inventory ledger entry.');
        }
    }

    /**
     * Update the specified inventory ledger entry.
     * Note: Ledger entries are typically immutable - use only for corrections.
     *
     * @param UpdateInventoryLedgerRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateInventoryLedgerRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $ledgerEntry = $this->inventoryLedgerService->updateLedgerEntry($id, $validatedData);
            
            return response()->json([
                'success' => true,
                'message' => 'Inventory ledger entry updated successfully.',
                'data' => new InventoryLedgerResource($ledgerEntry->load($this->getRelationships($request))),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to update inventory ledger entry', [
                'error' => $e->getMessage(),
                'id' => $id,
                'request' => $request->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update inventory ledger entry. Please try again.',
            ], 500);
        }
    }

    /**
     * Remove the specified inventory ledger entry.
     * Note: Use with extreme caution - ledger entries should be immutable.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->inventoryLedgerService->deleteLedgerEntry($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Inventory ledger entry deleted successfully.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to delete inventory ledger entry', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete inventory ledger entry. Please try again.',
            ], 500);
        }
    }

    /**
     * Verify a ledger entry.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'verified_by_staff_id' => 'required|integer|exists:staff,id',
                'notes' => 'nullable|string|max:500',
            ]);
            
            $verifiedByStaffId = $request->input('verified_by_staff_id');
            $notes = $request->input('notes');
            
            $ledgerEntry = $this->inventoryLedgerService->verifyLedgerEntry($id, $verifiedByStaffId, $notes);
            
            return response()->json([
                'success' => true,
                'message' => 'Inventory ledger entry verified successfully.',
                'data' => new InventoryLedgerResource($ledgerEntry->load($this->getRelationships($request))),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to verify inventory ledger entry', [
                'error' => $e->getMessage(),
                'id' => $id,
                'request' => $request->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify inventory ledger entry. Please try again.',
            ], 500);
        }
    }

    /**
     * Get current balance for an inventory item at a facility.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function currentBalance(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'facility_id' => 'required|integer|exists:facilities,id',
                'inventory_item_id' => 'required|integer|exists:inventory_items,id',
            ]);
            
            $facilityId = $request->input('facility_id');
            $inventoryItemId = $request->input('inventory_item_id');
            
            $balance = $this->inventoryLedgerService->getCurrentBalance($facilityId, $inventoryItemId);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'facility_id' => $facilityId,
                    'inventory_item_id' => $inventoryItemId,
                    'current_balance' => $balance,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to get current balance', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get current balance. Please try again.',
            ], 500);
        }
    }

    /**
     * Record a purchase transaction.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recordPurchase(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'facility_id' => 'required|integer|exists:facilities,id',
                'inventory_item_id' => 'required|integer|exists:inventory_items,id',
                'quantity' => 'required|numeric|min:0.01',
                'unit_of_measure' => 'required|string|max:50',
                'unit_cost_at_transaction' => 'nullable|numeric|min:0',
                'lot_number' => 'nullable|string|max:100',
                'expiry_date' => 'nullable|date|after_or_equal:today',
                'performed_by_staff_id' => 'required|integer|exists:staff,id',
                'reference_purchase_order_id' => 'nullable|integer',
                'transaction_notes' => 'nullable|string|max:1000',
            ]);
            
            $data = $request->only([
                'facility_id',
                'inventory_item_id',
                'unit_of_measure',
                'unit_cost_at_transaction',
                'lot_number',
                'expiry_date',
                'performed_by_staff_id',
                'reference_purchase_order_id',
                'transaction_notes',
            ]);
            
            $data['quantity_change'] = $request->input('quantity');
            $data['transaction_cause'] = 'manual_entry';
            
            if ($request->has('total_cost')) {
                $data['total_cost'] = $request->input('total_cost');
            } elseif ($request->has('unit_cost_at_transaction') && $request->has('quantity')) {
                $data['total_cost'] = $request->input('unit_cost_at_transaction') * $request->input('quantity');
            }
            
            $ledgerEntry = $this->inventoryLedgerService->recordPurchase($data);
            
            return response()->json([
                'success' => true,
                'message' => 'Purchase recorded successfully.',
                'data' => new InventoryLedgerResource($ledgerEntry->load($this->getRelationships($request))),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to record purchase', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to record purchase. Please try again.',
            ], 500);
        }
    }

    /**
     * Record a stock adjustment (increase or decrease).
     * User enters only the delta — system calculates the new balance.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recordAdjustment(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'facility_id' => 'required|integer|exists:facilities,id',
                'inventory_item_id' => 'required|integer|exists:inventory_items,id',
                'quantity' => 'required|numeric',
                'unit_of_measure' => 'required|string|max:50',
                'performed_by_staff_id' => 'nullable|integer|exists:staff,id',
                'transaction_notes' => 'nullable|string|max:1000',
                'lot_number' => 'nullable|string|max:100',
                'expiry_date' => 'nullable|date|after_or_equal:today',
            ]);

            $data = $request->only([
                'facility_id',
                'inventory_item_id',
                'unit_of_measure',
                'performed_by_staff_id',
                'transaction_notes',
                'lot_number',
                'expiry_date',
            ]);

            $data['quantity'] = $request->input('quantity');

            // If no staff ID provided, resolve from authenticated user
            if (empty($data['performed_by_staff_id'])) {
                $staff = $request->user()?->staff()->first();
                if ($staff) {
                    $data['performed_by_staff_id'] = $staff->id;
                }
            }

            $ledgerEntry = $this->inventoryLedgerService->recordAdjustment($data);

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully.',
                'data' => new InventoryLedgerResource($ledgerEntry->load($this->getRelationships($request))),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to record stock adjustment', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust stock. Please try again.',
            ], 500);
        }
    }

    /**
     * Get relationships to eager load based on request.
     *
     * @param Request $request
     * @return array
     */
    private function getRelationships(Request $request): array
    {
        $with = [];
        $includes = $request->get('include', '');
        
        if ($includes) {
            $includes = explode(',', $includes);
            $validIncludes = ['facility', 'inventoryItem', 'referenceVisit', 'performedByStaff', 'verifiedByStaff'];
            
            foreach ($includes as $include) {
                $include = trim($include);
                if (in_array($include, $validIncludes)) {
                    $with[] = $include;
                }
            }
        }
        
        return $with;
    }
}