<?php

declare(strict_types=1);

namespace App\Services\Lab;

use App\Models\LabRequest;
use App\Repositories\Lab\Contracts\LabRequestRepositoryInterface;
use App\Repositories\Lab\Contracts\LabRequestItemRepositoryInterface;
use App\Services\Lab\Contracts\LabRequestServiceInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LabRequestService implements LabRequestServiceInterface
{
    /**
     * @var LabRequestRepositoryInterface
     */
    protected LabRequestRepositoryInterface $requestRepository;

    /**
     * @var LabRequestItemRepositoryInterface
     */
    protected LabRequestItemRepositoryInterface $itemRepository;

    /**
     * Constructor.
     *
     * @param LabRequestRepositoryInterface $requestRepository
     * @param LabRequestItemRepositoryInterface $itemRepository
     */
    public function __construct(
        LabRequestRepositoryInterface $requestRepository,
        LabRequestItemRepositoryInterface $itemRepository
    ) {
        $this->requestRepository = $requestRepository;
        $this->itemRepository = $itemRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllRequests(array $filters = [], int $perPage = 20): array
    {
        try {
            $requests = $this->requestRepository->getAllPaginated($filters, $perPage);
            
            return [
                'success' => true,
                'message' => 'Lab requests retrieved successfully',
                'data' => [
                    'requests' => $requests,
                    'filters' => $filters,
                    'per_page' => $perPage,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve lab requests', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve lab requests',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestByUuid(string $uuid): array
    {
        try {
            $request = $this->requestRepository->findByUuid($uuid);
            
            if (!$request) {
                return [
                    'success' => false,
                    'message' => 'Lab request not found',
                    'error' => 'The requested lab request does not exist',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Lab request retrieved successfully',
                'data' => [
                    'request' => $request,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve lab request', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve lab request',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestById(int $id): array
    {
        try {
            $request = $this->requestRepository->findById($id);
            
            if (!$request) {
                return [
                    'success' => false,
                    'message' => 'Lab request not found',
                    'error' => 'The requested lab request does not exist',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Lab request retrieved successfully',
                'data' => [
                    'request' => $request,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve lab request', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve lab request',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
        */
    public function createRequest(array $data): array
    {
        try {
            // Automatically set the current staff user as creator
            $currentStaff = Auth::user()->staff;
            
            if (!$currentStaff) {
                return [
                    'success' => false,
                    'message' => 'Staff profile not found',
                    'error' => 'No staff record associated with authenticated user',
                    'data' => []
                ];
            }
            
            // Add audit fields
            $data['created_by_staff_id'] = $currentStaff->id;
            
            // If requested_by_staff_id is not provided, use current staff
            if (!isset($data['requested_by_staff_id'])) {
                $data['requested_by_staff_id'] = $currentStaff->id;
            }
            
            $request = $this->requestRepository->create($data);
            
            return [
                'success' => true,
                'message' => 'Lab request created successfully',
                'data' => ['request' => $request]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create lab request', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create lab request',
                'error' => 'An internal server error occurred',
                'data' => []
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateRequest(string $uuid, array $data): array
    {
        try {
            $request = $this->requestRepository->findByUuid($uuid);
            
            if (!$request) {
                return [
                    'success' => false,
                    'message' => 'Lab request not found',
                    'error' => 'The requested lab request does not exist',
                    'data' => [],
                ];
            }
            
            // Cannot update cancelled or completed requests
            if (in_array($request->status, ['cancelled', 'completed', 'reviewed'])) {
                return [
                    'success' => false,
                    'message' => 'Cannot update request',
                    'error' => 'This request is already ' . $request->status . ' and cannot be modified',
                    'data' => [],
                ];
            }
            
            // Validate relationships if they are being updated
            if (isset($data['visit_id']) || isset($data['patient_id']) || 
                isset($data['facility_id']) || isset($data['requested_by_staff_id'])) {
                if (!$this->validateRelationsExist($data)) {
                    return [
                        'success' => false,
                        'message' => 'Validation failed',
                        'error' => 'One or more referenced records do not exist',
                        'data' => [],
                    ];
                }
            }
            
            $updated = $this->requestRepository->update($request, $data);
            
            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'Failed to update lab request',
                    'error' => 'Unable to update lab request',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Lab request updated successfully',
                'data' => [
                    'request' => $request->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update lab request', [
                'uuid' => $uuid,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update lab request',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteRequest(string $uuid): array
    {
        try {
            $request = $this->requestRepository->findByUuid($uuid);
            
            if (!$request) {
                return [
                    'success' => false,
                    'message' => 'Lab request not found',
                    'error' => 'The requested lab request does not exist',
                    'data' => [],
                ];
            }
            
            // Check if request has items
            if ($request->items()->count() > 0) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete request with items',
                    'error' => 'Please delete all items from this request first',
                    'data' => [],
                ];
            }
            
            $deleted = $this->requestRepository->delete($request);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete lab request',
                    'error' => 'Unable to delete lab request',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Lab request deleted successfully',
                'data' => [],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete lab request', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to delete lab request',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateRequestStatus(string $uuid, string $status): array
    {
        try {
            $request = $this->requestRepository->findByUuid($uuid);
            
            if (!$request) {
                return [
                    'success' => false,
                    'message' => 'Lab request not found',
                    'error' => 'The requested lab request does not exist',
                    'data' => [],
                ];
            }
            
            // Validate status transition
            if (!$this->validateStatusTransition($request->status, $status)) {
                return [
                    'success' => false,
                    'message' => 'Invalid status transition',
                    'error' => "Cannot transition from {$request->status} to {$status}",
                    'data' => [],
                ];
            }
            
            $updated = $this->requestRepository->updateStatus($request, $status);
            
            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'Failed to update request status',
                    'error' => 'Unable to update request status',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Request status updated successfully',
                'data' => [
                    'request' => $request->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update request status', [
                'uuid' => $uuid,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update request status',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cancelRequest(string $uuid, string $reason, ?int $cancelledByStaffId = null): array
{
    try {
        // Validate reason
        if (empty(trim($reason))) {
            return [
                'success' => false,
                'message' => 'Cancellation reason is required',
                'error' => 'Please provide a reason for cancellation',
                'data' => [],
            ];
        }
        
        $request = $this->requestRepository->findByUuid($uuid);
        
        if (!$request) {
            return [
                'success' => false,
                'message' => 'Lab request not found',
                'error' => 'The requested lab request does not exist',
                'data' => [],
            ];
        }
        
        // Cannot cancel already finalised requests
        if (in_array($request->status, ['completed', 'reviewed', 'cancelled'])) {
            return [
                'success' => false,
                'message' => 'Cannot cancel request',
                'error' => 'This request is already ' . $request->status . ' and cannot be cancelled',
                'data' => [],
            ];
        }
        
        // Log in_progress cancellations for audit trail
        if ($request->status === 'in_progress') {
            Log::warning('Lab request cancelled while in progress', [
                'uuid' => $uuid,
                'reason' => $reason,
                'cancelled_by' => $cancelledByStaffId,
                'original_status' => $request->status
            ]);
        }
        
        $cancelled = $this->requestRepository->cancel($request, $reason, $cancelledByStaffId);
        
        if (!$cancelled) {
            return [
                'success' => false,
                'message' => 'Failed to cancel request',
                'error' => 'Unable to cancel request. Please try again.',
                'data' => [],
            ];
        }
        
        // Set appropriate message based on status
        $message = $request->status === 'in_progress' 
            ? 'Request cancelled successfully. Note: This request was already in progress.'
            : 'Request cancelled successfully';
        
        return [
            'success' => true,
            'message' => $message,
            'data' => [
                'request' => $request->fresh(),
            ],
        ];
        
    } catch (\Exception $e) {
        Log::error('Failed to cancel request', [
            'uuid' => $uuid,
            'reason' => $reason,
            'error' => $e->getMessage(),
        ]);
        
        return [
            'success' => false,
            'message' => 'Failed to cancel request',
            'error' => 'An internal server error occurred',
            'data' => [],
        ];
    }
}

    /**
     * {@inheritdoc}
     */
    public function getRequestsByFacility(int $facilityId, array $filters = [], int $perPage = 20): array
    {
        try {
            $requests = $this->requestRepository->getByFacility($facilityId, $filters, $perPage);
            
            return [
                'success' => true,
                'message' => 'Lab requests retrieved successfully',
                'data' => [
                    'requests' => $requests,
                    'facility_id' => $facilityId,
                    'filters' => $filters,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve requests by facility', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve lab requests',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestsByPatient(int $patientId, array $filters = [], int $perPage = 20): array
    {
        try {
            $requests = $this->requestRepository->getByPatient($patientId, $filters, $perPage);
            
            return [
                'success' => true,
                'message' => 'Lab requests retrieved successfully',
                'data' => [
                    'requests' => $requests,
                    'patient_id' => $patientId,
                    'filters' => $filters,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve requests by patient', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve lab requests',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestsByVisit(int $visitId): array
    {
        try {
            $requests = $this->requestRepository->getByVisit($visitId);
            
            return [
                'success' => true,
                'message' => 'Lab requests retrieved successfully',
                'data' => [
                    'requests' => $requests,
                    'visit_id' => $visitId,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve requests by visit', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve lab requests',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestsByStatus(string $status, ?int $facilityId = null): array
    {
        try {
            $requests = $this->requestRepository->getByStatus($status, $facilityId);
            
            return [
                'success' => true,
                'message' => 'Lab requests retrieved successfully',
                'data' => [
                    'requests' => $requests,
                    'status' => $status,
                    'facility_id' => $facilityId,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve requests by status', [
                'status' => $status,
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve lab requests',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPendingRequests(?int $facilityId = null): array
    {
        try {
            $requests = $this->requestRepository->getPendingRequests($facilityId);
            
            return [
                'success' => true,
                'message' => 'Pending requests retrieved successfully',
                'data' => [
                    'requests' => $requests,
                    'facility_id' => $facilityId,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve pending requests', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve pending requests',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestsRequiringAttention(int $facilityId): array
    {
        try {
            $requests = $this->requestRepository->getRequestsRequiringAttention($facilityId);
            
            return [
                'success' => true,
                'message' => 'Requests requiring attention retrieved successfully',
                'data' => [
                    'requests' => $requests,
                    'facility_id' => $facilityId,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve requests requiring attention', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve requests requiring attention',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */

public function getRequestWithItems(string $uuid): array
{
    try {
        // Use findByUuid which already loads all relationships including items.labTest
        $request = $this->requestRepository->findByUuid($uuid);
        
        if (!$request) {
            return [
                'success' => false,
                'message' => 'Lab request not found',
                'error' => 'The requested lab request does not exist',
                'data' => [],
            ];
        }
        
        // Just return the request that already has everything loaded
        return [
            'success' => true,
            'message' => 'Lab request with items retrieved successfully',
            'data' => [
                'request' => $request,  // Use the already-loaded request
            ],
        ];
    } catch (\Exception $e) {
        Log::error('Failed to retrieve request with items', [
            'uuid' => $uuid,
            'error' => $e->getMessage(),
        ]);
        
        return [
            'success' => false,
            'message' => 'Failed to retrieve lab request',
            'error' => 'An internal server error occurred',
            'data' => [],
        ];
    }
}

    /**
     * {@inheritdoc}
     */
    public function getRequestWithFullDetails(string $uuid): array
{
    try {
        // Use findByUuid or create a dedicated method that loads all nested relationships
        $request = $this->requestRepository->findByUuid($uuid);
        
        if (!$request) {
            return [
                'success' => false,
                'message' => 'Lab request not found',
                'error' => 'The requested lab request does not exist',
                'data' => [],
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Lab request with full details retrieved successfully',
            'data' => [
                'request' => $request,
            ],
        ];
    } catch (\Exception $e) {
        Log::error('Failed to retrieve request with full details', [
            'uuid' => $uuid,
            'error' => $e->getMessage(),
        ]);
        
        return [
            'success' => false,
            'message' => 'Failed to retrieve lab request details',
            'error' => 'An internal server error occurred',
            'data' => [],
        ];
    }
}

    /**
     * {@inheritdoc}
     */
    public function getRequestStatistics(int $facilityId, string $startDate, string $endDate): array
    {
        try {
            $statistics = $this->requestRepository->getRequestStatistics($facilityId, $startDate, $endDate);
            
            return [
                'success' => true,
                'message' => 'Request statistics retrieved successfully',
                'data' => $statistics,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve request statistics', [
                'facility_id' => $facilityId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve request statistics',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createRequestWithItems(array $requestData, array $itemsData): array
    {
        try {
            return DB::transaction(function () use ($requestData, $itemsData) {
                // Create the request
                $request = $this->requestRepository->create($requestData);
                
                if (!$request) {
                    return [
                        'success' => false,
                        'message' => 'Failed to create request',
                        'error' => 'Unable to create the lab request',
                        'data' => [],
                    ];
                }
                
                // Create items for the request
                if (!empty($itemsData)) {
                    $items = $this->itemRepository->bulkCreate($request->id, $itemsData);
                    
                    return [
                        'success' => true,
                        'message' => 'Lab request with items created successfully',
                        'data' => [
                            'request' => $request->fresh(),
                            'items' => $items,
                            'items_count' => count($items),
                        ],
                    ];
                }
                
                return [
                    'success' => true,
                    'message' => 'Lab request created successfully (no items)',
                    'data' => [
                        'request' => $request,
                        'items' => [],
                        'items_count' => 0,
                    ],
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to create request with items', [
                'request_data' => $requestData,
                'items_count' => count($itemsData),
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create lab request with items',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
        public function addItemsToRequest(string $requestUuid, array $itemsData): array
    {
        try {
            $request = $this->requestRepository->findByUuid($requestUuid);
            
            if (!$request) {
                return [
                    'success' => false,
                    'message' => 'Lab request not found',
                    'data' => []
                ];
            }
            
            // Can't add items to cancelled or completed requests
            if (in_array($request->status, ['cancelled', 'completed', 'reviewed'])) {
                return [
                    'success' => false,
                    'message' => 'Cannot add items to request',
                    'error' => 'This request is already ' . $request->status,
                    'data' => []
                ];
            }
            
            // Get current staff for audit
            $currentStaff = Auth::user()->staff;
            
            // Add audit fields to each item
            foreach ($itemsData as &$item) {
                $item['created_by_staff_id'] = $currentStaff?->id;
                $item['updated_by_staff_id'] = $currentStaff?->id;
            }
            
            $items = $this->itemRepository->bulkCreate($request->id, $itemsData);
            
            return [
                'success' => true,
                'message' => count($items) . ' Lab Tests added to the current Lab request.',
                'data' => [
                    'request' => $request->fresh(),
                    'items' => $items,
                    'items_added' => count($items)
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to add items to request', [
                'request_uuid' => $requestUuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to add items to request',
                'data' => []
            ];
        }
    }

    /**
     * Validate that referenced relationships exist.
     *
     * @param array $data
     * @return bool
     */
    protected function validateRelationsExist(array $data): bool
    {
        // Validation handled by the request files.
        
        return true;
    }

    /**
     * Validate status transition.
     *
     * @param string $currentStatus
     * @param string $newStatus
     * @return bool
     */
    protected function validateStatusTransition(string $currentStatus, string $newStatus): bool
    {
        $allowedTransitions = [
            'pending' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => ['reviewed'],
            'reviewed' => [],
            'cancelled' => [],
        ];
        
        return in_array($newStatus, $allowedTransitions[$currentStatus] ?? []);
    }
}