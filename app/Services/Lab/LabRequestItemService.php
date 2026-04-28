<?php

declare(strict_types=1);

namespace App\Services\Lab;

use App\Models\LabRequestItem;
use App\Repositories\Lab\Contracts\LabRequestItemRepositoryInterface;
use App\Repositories\Lab\Contracts\LabRequestRepositoryInterface;
use App\Repositories\Lab\Contracts\LabTestRepositoryInterface;
use App\Services\Lab\Contracts\LabRequestItemServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LabRequestItemService implements LabRequestItemServiceInterface
{
    /**
     * @var LabRequestItemRepositoryInterface
     */
    protected LabRequestItemRepositoryInterface $itemRepository;

    /**
     * @var LabRequestRepositoryInterface
     */
    protected LabRequestRepositoryInterface $requestRepository;

    /**
     * @var LabTestRepositoryInterface
     */
    protected LabTestRepositoryInterface $testRepository;

    /**
     * Constructor.
     *
     * @param LabRequestItemRepositoryInterface $itemRepository
     * @param LabRequestRepositoryInterface $requestRepository
     * @param LabTestRepositoryInterface $testRepository
     */
    public function __construct(
        LabRequestItemRepositoryInterface $itemRepository,
        LabRequestRepositoryInterface $requestRepository,
        LabTestRepositoryInterface $testRepository
    ) {
        $this->itemRepository = $itemRepository;
        $this->requestRepository = $requestRepository;
        $this->testRepository = $testRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllItems(array $filters = [], int $perPage = 20): array
    {
        try {
            $items = $this->itemRepository->getAllPaginated($filters, $perPage);
            
            return [
                'success' => true,
                'message' => 'Lab request items retrieved successfully',
                'data' => [
                    'items' => $items,
                    'filters' => $filters,
                    'per_page' => $perPage,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve lab request items', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve lab request items',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItemByUuid(string $uuid): array
    {
        try {
            $item = $this->itemRepository->findByUuid($uuid);
            
            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'Lab request item not found',
                    'error' => 'The requested lab request item does not exist',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Lab request item retrieved successfully',
                'data' => [
                    'item' => $item,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve lab request item', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve lab request item',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItemById(int $id): array
    {
        try {
            $item = $this->itemRepository->findById($id);
            
            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'Lab request item not found',
                    'error' => 'The requested lab request item does not exist',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Lab request item retrieved successfully',
                'data' => [
                    'item' => $item,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve lab request item', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve lab request item',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createItem(array $data): array
    {
        try {
            // Validate lab request exists
            $request = $this->requestRepository->findById($data['lab_request_id']);
            if (!$request) {
                return [
                    'success' => false,
                    'message' => 'Lab request not found',
                    'error' => 'The specified lab request does not exist',
                    'data' => [],
                ];
            }
            
            // Cannot add items to cancelled or completed requests
            if (in_array($request->status, ['cancelled', 'completed', 'reviewed'])) {
                return [
                    'success' => false,
                    'message' => 'Cannot add item to request',
                    'error' => 'This request is already ' . $request->status . ' and cannot be modified',
                    'data' => [],
                ];
            }
            
            // Validate lab test exists
            $test = $this->testRepository->findById($data['lab_test_id']);
            if (!$test) {
                return [
                    'success' => false,
                    'message' => 'Lab test not found',
                    'error' => 'The specified lab test does not exist',
                    'data' => [],
                ];
            }
            
            $item = $this->itemRepository->create($data);
            
            return [
                'success' => true,
                'message' => 'Lab request item created successfully',
                'data' => [
                    'item' => $item,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create lab request item', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create lab request item',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateItem(string $uuid, array $data): array
    {
        try {
            $item = $this->itemRepository->findByUuid($uuid);
            
            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'Lab request item not found',
                    'error' => 'The requested lab request item does not exist',
                    'data' => [],
                ];
            }
            
            $request = $this->requestRepository->findById($item->lab_request_id);
            
            // Cannot update items of cancelled or completed requests
            if (in_array($request->status, ['cancelled', 'completed', 'reviewed'])) {
                return [
                    'success' => false,
                    'message' => 'Cannot update item',
                    'error' => 'The parent request is already ' . $request->status . ' and cannot be modified',
                    'data' => [],
                ];
            }
            
            // Cannot update items that are already verified
            if ($item->isVerified()) {
                return [
                    'success' => false,
                    'message' => 'Cannot update verified item',
                    'error' => 'This item has already been verified and cannot be modified',
                    'data' => [],
                ];
            }
            
            // Validate lab test if being updated
            if (isset($data['lab_test_id'])) {
                $test = $this->testRepository->findById($data['lab_test_id']);
                if (!$test) {
                    return [
                        'success' => false,
                        'message' => 'Lab test not found',
                        'error' => 'The specified lab test does not exist',
                        'data' => [],
                    ];
                }
            }
            
            $updated = $this->itemRepository->update($item, $data);
            
            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'Failed to update lab request item',
                    'error' => 'Unable to update lab request item',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Lab request item updated successfully',
                'data' => [
                    'item' => $item->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update lab request item', [
                'uuid' => $uuid,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update lab request item',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem(string $uuid): array
    {
        try {
            $item = $this->itemRepository->findByUuid($uuid);
            
            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'Lab request item not found',
                    'error' => 'The requested lab request item does not exist',
                    'data' => [],
                ];
            }
            
            $request = $this->requestRepository->findById($item->lab_request_id);
            
            // Cannot delete items of cancelled or completed requests
            if (in_array($request->status, ['cancelled', 'completed', 'reviewed'])) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete item',
                    'error' => 'The parent request is already ' . $request->status . ' and cannot be modified',
                    'data' => [],
                ];
            }
            
            // Check if item has results
            if ($item->results()->count() > 0) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete item with results',
                    'error' => 'Please delete all results from this item first',
                    'data' => [],
                ];
            }
            
            $deleted = $this->itemRepository->delete($item);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete lab request item',
                    'error' => 'Unable to delete lab request item',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Lab request item deleted successfully',
                'data' => [],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete lab request item', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to delete lab request item',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateItemStatus(string $uuid, string $status): array
    {
        try {
            $item = $this->itemRepository->findByUuid($uuid);
            
            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'Lab request item not found',
                    'error' => 'The requested lab request item does not exist',
                    'data' => [],
                ];
            }
            
            // Validate status transition
            if (!$this->validateItemStatusTransition($item->status, $status)) {
                return [
                    'success' => false,
                    'message' => 'Invalid status transition',
                    'error' => "Cannot transition from {$item->status} to {$status}",
                    'data' => [],
                ];
            }
            
            $updated = $this->itemRepository->updateStatus($item, $status);
            
            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'Failed to update item status',
                    'error' => 'Unable to update item status',
                    'data' => [],
                ];
            }
            
            // Update parent request status if needed
            $this->updateParentRequestStatus($item->lab_request_id);
            
            return [
                'success' => true,
                'message' => 'Item status updated successfully',
                'data' => [
                    'item' => $item->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update item status', [
                'uuid' => $uuid,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update item status',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function markSampleCollected(string $uuid, int $collectedByStaffId, ?string $sampleIdentifier = null): array
    {
        try {
            $item = $this->itemRepository->findByUuid($uuid);
            
            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'Lab request item not found',
                    'error' => 'The requested lab request item does not exist',
                    'data' => [],
                ];
            }
            
            if (!$item->isPending()) {
                return [
                    'success' => false,
                    'message' => 'Cannot collect sample',
                    'error' => 'Sample can only be collected for pending items',
                    'data' => [],
                ];
            }
            
            $collected = $this->itemRepository->markSampleCollected($item, $collectedByStaffId, $sampleIdentifier);
            
            if (!$collected) {
                return [
                    'success' => false,
                    'message' => 'Failed to mark sample as collected',
                    'error' => 'Unable to update sample collection status',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Sample marked as collected successfully',
                'data' => [
                    'item' => $item->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to mark sample as collected', [
                'uuid' => $uuid,
                'collected_by_staff_id' => $collectedByStaffId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to mark sample as collected',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function markItemInProgress(string $uuid): array
    {
        try {
            $item = $this->itemRepository->findByUuid($uuid);
            
            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'Lab request item not found',
                    'error' => 'The requested lab request item does not exist',
                    'data' => [],
                ];
            }
            
            if (!$item->isSampleCollected()) {
                return [
                    'success' => false,
                    'message' => 'Cannot start processing',
                    'error' => 'Sample must be collected before processing',
                    'data' => [],
                ];
            }
            
            $started = $this->itemRepository->updateStatus($item, 'in_progress');
            
            if (!$started) {
                return [
                    'success' => false,
                    'message' => 'Failed to start processing',
                    'error' => 'Unable to update item status',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Item processing started successfully',
                'data' => [
                    'item' => $item->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to mark item as in progress', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to start processing',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function markItemCompleted(string $uuid): array
    {
        try {
            $item = $this->itemRepository->findByUuid($uuid);
            
            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'Lab request item not found',
                    'error' => 'The requested lab request item does not exist',
                    'data' => [],
                ];
            }
            
            if (!$item->isInProgress()) {
                return [
                    'success' => false,
                    'message' => 'Cannot complete item',
                    'error' => 'Item must be in progress to mark as completed',
                    'data' => [],
                ];
            }
            
            $completed = $this->itemRepository->updateStatus($item, 'completed');
            
            if (!$completed) {
                return [
                    'success' => false,
                    'message' => 'Failed to complete item',
                    'error' => 'Unable to update item status',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Item completed successfully',
                'data' => [
                    'item' => $item->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to mark item as completed', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to complete item',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function markItemVerified(string $uuid, int $verifiedByStaffId): array
    {
        try {
            $item = $this->itemRepository->findByUuid($uuid);
            
            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'Lab request item not found',
                    'error' => 'The requested lab request item does not exist',
                    'data' => [],
                ];
            }
            
            if (!$item->isCompleted()) {
                return [
                    'success' => false,
                    'message' => 'Cannot verify item',
                    'error' => 'Item must be completed before verification',
                    'data' => [],
                ];
            }
            
            // Check if all results are verified
            if (!$item->areAllResultsVerified()) {
                return [
                    'success' => false,
                    'message' => 'Cannot verify item',
                    'error' => 'All results must be verified before marking item as verified',
                    'data' => [],
                ];
            }
            
            $verified = $this->itemRepository->markVerified($item, $verifiedByStaffId);
            
            if (!$verified) {
                return [
                    'success' => false,
                    'message' => 'Failed to verify item',
                    'error' => 'Unable to verify item',
                    'data' => [],
                ];
            }
            
            // Update parent request status
            $this->updateParentRequestStatus($item->lab_request_id);
            
            return [
                'success' => true,
                'message' => 'Item verified successfully',
                'data' => [
                    'item' => $item->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to verify item', [
                'uuid' => $uuid,
                'verified_by_staff_id' => $verifiedByStaffId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to verify item',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cancelItem(string $uuid, string $reason, ?int $cancelledByStaffId = null): array
    {
        try {
            $item = $this->itemRepository->findByUuid($uuid);
            
            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'Lab request item not found',
                    'error' => 'The requested lab request item does not exist',
                    'data' => [],
                ];
            }
            
            if ($item->isVerified()) {
                return [
                    'success' => false,
                    'message' => 'Cannot cancel verified item',
                    'error' => 'Verified items cannot be cancelled',
                    'data' => [],
                ];
            }
            
            $cancelled = $this->itemRepository->cancel($item, $reason, $cancelledByStaffId);
            
            if (!$cancelled) {
                return [
                    'success' => false,
                    'message' => 'Failed to cancel item',
                    'error' => 'Unable to cancel item',
                    'data' => [],
                ];
            }
            
            // Update parent request status
            $this->updateParentRequestStatus($item->lab_request_id);
            
            return [
                'success' => true,
                'message' => 'Item cancelled successfully',
                'data' => [
                    'item' => $item->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to cancel item', [
                'uuid' => $uuid,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to cancel item',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItemsByLabRequest(string $requestUuid, array $filters = []): array
    {
        try {
            $request = $this->requestRepository->findByUuid($requestUuid);
            
            if (!$request) {
                return [
                    'success' => false,
                    'message' => 'Lab request not found',
                    'error' => 'The requested lab request does not exist',
                    'data' => [],
                ];
            }
            
            $items = $this->itemRepository->getByLabRequest($request->id, $filters);
            
            return [
                'success' => true,
                'message' => 'Items retrieved successfully',
                'data' => [
                    'items' => $items,
                    'request' => $request,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve items by lab request', [
                'request_uuid' => $requestUuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve items',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItemsByLabTest(string $testUuid, array $filters = []): array
    {
        try {
            $test = $this->testRepository->findByUuid($testUuid);
            
            if (!$test) {
                return [
                    'success' => false,
                    'message' => 'Lab test not found',
                    'error' => 'The requested lab test does not exist',
                    'data' => [],
                ];
            }
            
            $items = $this->itemRepository->getByLabTest($test->id, $filters);
            
            return [
                'success' => true,
                'message' => 'Items retrieved successfully',
                'data' => [
                    'items' => $items,
                    'test' => $test,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve items by lab test', [
                'test_uuid' => $testUuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve items',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPendingItems(?int $facilityId = null): array
    {
        try {
            $items = $this->itemRepository->getPendingItems($facilityId);
            
            return [
                'success' => true,
                'message' => 'Pending items retrieved successfully',
                'data' => [
                    'items' => $items,
                    'facility_id' => $facilityId,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve pending items', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve pending items',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItemsWithAbnormalResults(?int $facilityId = null): array
    {
        try {
            $items = $this->itemRepository->getAbnormalOrCriticalItems($facilityId);
            
            return [
                'success' => true,
                'message' => 'Items with abnormal results retrieved successfully',
                'data' => [
                    'items' => $items,
                    'facility_id' => $facilityId,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve items with abnormal results', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve items',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItemsAwaitingVerification(?int $facilityId = null): array
    {
        try {
            $items = $this->itemRepository->getItemsAwaitingVerification($facilityId);
            
            return [
                'success' => true,
                'message' => 'Items awaiting verification retrieved successfully',
                'data' => [
                    'items' => $items,
                    'facility_id' => $facilityId,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve items awaiting verification', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve items',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItemWithResults(string $uuid): array
    {
        try {
            $item = $this->itemRepository->findByUuid($uuid);
            
            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'Lab request item not found',
                    'error' => 'The requested lab request item does not exist',
                    'data' => [],
                ];
            }
            
            $itemWithResults = $this->itemRepository->getWithResults($item->id);
            
            return [
                'success' => true,
                'message' => 'Item with results retrieved successfully',
                'data' => [
                    'item' => $itemWithResults,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve item with results', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve item',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItemWithFullDetails(string $uuid): array
    {
        try {
            $item = $this->itemRepository->findByUuid($uuid);
            
            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'Lab request item not found',
                    'error' => 'The requested lab request item does not exist',
                    'data' => [],
                ];
            }
            
            $itemWithDetails = $this->itemRepository->getWithFullDetails($item->id);
            
            return [
                'success' => true,
                'message' => 'Item with full details retrieved successfully',
                'data' => [
                    'item' => $itemWithDetails,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve item with full details', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve item details',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTurnaroundTimeStatistics(string $testUuid, string $startDate, string $endDate): array
    {
        try {
            $test = $this->testRepository->findByUuid($testUuid);
            
            if (!$test) {
                return [
                    'success' => false,
                    'message' => 'Lab test not found',
                    'error' => 'The requested lab test does not exist',
                    'data' => [],
                ];
            }
            
            $statistics = $this->itemRepository->getTurnaroundTimeStatistics($test->id, $startDate, $endDate);
            
            return [
                'success' => true,
                'message' => 'Turnaround time statistics retrieved successfully',
                'data' => $statistics,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve turnaround time statistics', [
                'test_uuid' => $testUuid,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bulkUpdateItemsStatus(array $uuids, string $status): array
    {
        try {
            $updatedCount = 0;
            $failedItems = [];
            
            foreach ($uuids as $uuid) {
                $result = $this->updateItemStatus($uuid, $status);
                if ($result['success']) {
                    $updatedCount++;
                } else {
                    $failedItems[] = $uuid;
                }
            }
            
            return [
                'success' => true,
                'message' => "{$updatedCount} items updated successfully",
                'data' => [
                    'updated_count' => $updatedCount,
                    'total_attempted' => count($uuids),
                    'failed_items' => $failedItems,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to bulk update items status', [
                'uuids' => $uuids,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update items',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * Validate item status transition.
     *
     * @param string $currentStatus
     * @param string $newStatus
     * @return bool
     */
    protected function validateItemStatusTransition(string $currentStatus, string $newStatus): bool
    {
        $allowedTransitions = [
            'pending' => ['sample_collected', 'cancelled'],
            'sample_collected' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => ['verified', 'cancelled'],
            'verified' => [],
            'cancelled' => [],
        ];
        
        return in_array($newStatus, $allowedTransitions[$currentStatus] ?? []);
    }

    /**
     * Update parent request status based on its items.
     *
     * @param int $requestId
     * @return void
     */
    protected function updateParentRequestStatus(int $requestId): void
    {
        $request = $this->requestRepository->findById($requestId);
        
        if (!$request || in_array($request->status, ['cancelled', 'reviewed'])) {
            return;
        }
        
        $items = $request->items;
        $totalItems = $items->count();
        $verifiedItems = $items->where('status', 'verified')->count();
        $completedItems = $items->where('status', 'completed')->count();
        $cancelledItems = $items->where('status', 'cancelled')->count();
        $inProgressItems = $items->where('status', 'in_progress')->count();
        
        if ($verifiedItems === $totalItems && $totalItems > 0) {
            $this->requestRepository->updateStatus($request, 'reviewed');
        } elseif ($completedItems + $cancelledItems === $totalItems) {
            $this->requestRepository->updateStatus($request, 'completed');
        } elseif ($inProgressItems > 0 || $completedItems > 0) {
            $this->requestRepository->updateStatus($request, 'in_progress');
        }
    }
}