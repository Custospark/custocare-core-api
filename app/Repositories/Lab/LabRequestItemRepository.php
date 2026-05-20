<?php

declare(strict_types=1);

namespace App\Repositories\Lab;

use App\Models\LabRequestItem;
use App\Models\Staff;
use App\Repositories\Lab\Contracts\LabRequestItemRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LabRequestItemRepository implements LabRequestItemRepositoryInterface
{
    /**
     * @var LabRequestItem
     */
    protected LabRequestItem $model;

    /**
     * Constructor.
     *
     * @param LabRequestItem $model
     */
    public function __construct(LabRequestItem $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?LabRequestItem
    {
        return $this->model->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findByUuid(string $uuid): ?LabRequestItem
    {
        return $this->model->where('item_uuid', $uuid)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function findBySampleIdentifier(string $sampleIdentifier): ?LabRequestItem
    {
        return $this->model->where('sample_identifier', $sampleIdentifier)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->query();

        if (!empty($filters['lab_request_id'])) {
            $query->where('lab_request_id', $filters['lab_request_id']);
        }

        if (!empty($filters['lab_test_id'])) {
            $query->where('lab_test_id', $filters['lab_test_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['result_flag'])) {
            $query->withResultFlag($filters['result_flag']);
        }

        if (isset($filters['has_abnormal_results']) && $filters['has_abnormal_results']) {
            $query->abnormalOrCritical();
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $orderBy = $filters['order_by'] ?? 'created_at';
        $orderDirection = $filters['order_direction'] ?? 'desc';
        $query->orderBy($orderBy, $orderDirection);

        return $query->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getByLabRequest(int $labRequestId, array $filters = []): Collection
    {
        $query = $this->model->where('lab_request_id', $labRequestId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByLabTest(int $labTestId, array $filters = []): Collection
    {
        $query = $this->model->where('lab_test_id', $labTestId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByStatus(string $status, ?int $labRequestId = null): Collection
    {
        $query = $this->model->where('status', $status);
        
        if ($labRequestId) {
            $query->where('lab_request_id', $labRequestId);
        }
        
        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getPendingItems(?int $facilityId = null): Collection
    {
        $query = $this->model->pending();
        
        if ($facilityId) {
            $query->whereHas('labRequest', function ($q) use ($facilityId) {
                $q->byFacility($facilityId);
            });
        }
        
        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getAbnormalOrCriticalItems(?int $facilityId = null): Collection
    {
        $query = $this->model->abnormalOrCritical();
        
        if ($facilityId) {
            $query->whereHas('labRequest', function ($q) use ($facilityId) {
                $q->byFacility($facilityId);
            });
        }
        
        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getItemsAwaitingVerification(?int $facilityId = null): Collection
    {
        $query = $this->model->completed()->whereNull('verified_at');
        
        if ($facilityId) {
            $query->whereHas('labRequest', function ($q) use ($facilityId) {
                $q->byFacility($facilityId);
            });
        }
        
        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): LabRequestItem
    {
        return DB::transaction(function () use ($data) {
            $data['created_by_staff_id']=Staff::where('user_id', Auth::id())->value('id');
            $data['updated_by_staff_id'] =Staff::where('user_id', Auth::id())->value('id');
            return $this->model->create($data);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function bulkCreate(int $labRequestId, array $itemsData): array
    {
        return DB::transaction(function () use ($labRequestId, $itemsData) {
            $createdItems = [];
            
            foreach ($itemsData as $itemData) {
                // Prepare item data with proper defaults
                $item = [
                    'lab_request_id' => $labRequestId,
                    'lab_test_id' => $itemData['lab_test_id'],
                    'status' => 'pending',
                    'result_flag' => 'pending',
                    'sample_type' => $itemData['sample_type'] ?? null,
                    'notes' => $itemData['notes'] ?? null,
                    'created_by_staff_id' => $itemData['created_by_staff_id'] ?? Staff::where('user_id', Auth::id())->value('id'),
                    'updated_by_staff_id' => $itemData['updated_by_staff_id'] ??  Staff::where('user_id', Auth::id())->value('id'),
                    'metadata' => $itemData['metadata'] ?? null,
                ];
                
                $createdItems[] = $this->model->create($item);
            }
            
            return $createdItems;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(LabRequestItem $item, array $data): bool
    {
        return DB::transaction(function () use ($item, $data) {
            return $item->update($data);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function delete(LabRequestItem $item): bool
    {
        return DB::transaction(function () use ($item) {
            return $item->delete();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function restore(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            return $this->model->withTrashed()->find($id)?->restore() ?? false;
        });
    }

    /**
     * {@inheritdoc}
     *//**
 * Update the status of a lab request item with proper audit tracking.
 * This method automatically sets timestamps and staff IDs based on the status transition.
 *
 * @param LabRequestItem $item
 * @param string $status
 * @return bool
 */
public function updateStatus(LabRequestItem $item, string $status): bool
{
    return DB::transaction(function () use ($item, $status) {
        $oldStatus = $item->status;
        $currentStaffId = $this->getCurrentStaffId();
        
        // Log the status change for audit purposes
        Log::info('Lab Request Item Status Update', [
            'item_uuid' => $item->item_uuid,
            'old_status' => $oldStatus,
            'new_status' => $status,
            'staff_id' => $currentStaffId,
            'timestamp' => now()->toIso8601String()
        ]);
        
        $item->status = $status;
        
        // Mark relevant fields based on the new status
        switch ($status) {
            case 'sample_collected':
                if (is_null($item->collected_at)) {
                    $item->collected_at = now();
                }
                if (is_null($item->collected_by_staff_id)) {
                    $item->collected_by_staff_id = $currentStaffId;
                }
                break;
                
            case 'in_progress':
                if (is_null($item->started_at)) {
                    $item->started_at = now();
                }
                if (is_null($item->started_by_staff_id)) {
                    $item->started_by_staff_id = $currentStaffId;
                }
                break;
                
            case 'completed':
                if (is_null($item->completed_at)) {
                    $item->completed_at = now();
                }
                if (is_null($item->completed_by_staff_id)) {
                    $item->completed_by_staff_id = $currentStaffId;
                }
                break;
                
            case 'verified':
                if (is_null($item->verified_at)) {
                    $item->verified_at = now();
                }
                if (is_null($item->verified_by_staff_id)) {
                    $item->verified_by_staff_id = $currentStaffId;
                }
                break;
                
            case 'cancelled':
                if (is_null($item->cancelled_at)) {
                    $item->cancelled_at = now();
                }
                if (is_null($item->cancelled_by_staff_id)) {
                    $item->cancelled_by_staff_id = $currentStaffId;
                }
                break;
        }
        
        // Always update the updated_by_staff_id for audit trail
        $item->updated_by_staff_id = $currentStaffId;
        
        $saved = $item->save();
        
        if ($saved) {
            Log::info('Lab Request Item Status Updated Successfully', [
                'item_uuid' => $item->item_uuid,
                'new_status' => $status,
                'staff_id' => $currentStaffId
            ]);
        }
        
        return $saved;
    });
}

/**
 * Get the current authenticated staff ID
 */
    protected function getCurrentStaffId(): ?int
    {
        $staff = \App\Models\Staff::where('user_id', auth()->id())->first();
        return $staff?->id;
    }

    /**
     * {@inheritdoc}
     */
    public function markSampleCollected(LabRequestItem $item, int $collectedByStaffId, ?string $sampleIdentifier = null): bool
    {
        return DB::transaction(function () use ($item, $collectedByStaffId, $sampleIdentifier) {
            return $item->markSampleCollected($collectedByStaffId, $sampleIdentifier);
        });
    }

    /**
     * {@inheritdoc}
     */
   /**
 * Mark item as verified.
 *
 * @param LabRequestItem $item
 * @param int $verifiedByStaffId
 * @return bool
 */
   public function markVerified(LabRequestItem $item, int $verifiedByStaffId): bool
{
    return DB::transaction(function () use ($item, $verifiedByStaffId) {
        $item->status = 'verified';
        $item->verified_at = now();
        
        // Use provided staff ID, otherwise get from authenticated user
        if ($verifiedByStaffId) {
            $item->verified_by_staff_id = $verifiedByStaffId;
        } else {
            $item->verified_by_staff_id = Staff::where('user_id', Auth::id())->value('id');
        }
        
        // Also update the updated_by_staff_id for audit trail
        $item->updated_by_staff_id = $item->verified_by_staff_id;
        
        // Update the result_flag based on all results
        $item->updateResultFlagFromResults();
        
        return $item->save();
    });
}

    /**
     * {@inheritdoc}
     */
 public function cancel(LabRequestItem $item, string $reason, ?int $cancelledByStaffId = null): bool
{
    return DB::transaction(function () use ($item, $reason, $cancelledByStaffId) {
        $staffId = $cancelledByStaffId ?? Auth::user()->staff->id;
        return $item->cancel($reason, $staffId);
    });
}

    /**
     * {@inheritdoc}
     */
    public function getWithResults(int $id): ?LabRequestItem
    {
        return $this->model->with('results')->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function getWithFullDetails(int $id): ?LabRequestItem
    {
        return $this->model->with([
            'labRequest',
            'labTest.template',
            'collectedBy',
            'startedBy',
            'completedBy',
            'verifiedBy',
            'cancelledBy',
            'createdBy',
            'results'
        ])->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function getByDateRange(string $startDate, string $endDate, ?int $facilityId = null): Collection
    {
        $query = $this->model->whereBetween('created_at', [$startDate, $endDate]);
        
        if ($facilityId) {
            $query->whereHas('labRequest', function ($q) use ($facilityId) {
                $q->byFacility($facilityId);
            });
        }
        
        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getTurnaroundTimeStatistics(int $labTestId, string $startDate, string $endDate): array
    {
        $items = $this->model->where('lab_test_id', $labTestId)
            ->whereNotNull('collected_at')
            ->whereNotNull('completed_at')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        if ($items->isEmpty()) {
            return [
                'average_minutes' => 0,
                'min_minutes' => 0,
                'max_minutes' => 0,
                'total_samples' => 0,
            ];
        }

        $turnaroundTimes = $items->map(function ($item) {
            return $item->getCollectionToCompletionMinutesAttribute();
        })->filter();

        return [
            'average_minutes' => round($turnaroundTimes->avg(), 2),
            'min_minutes' => $turnaroundTimes->min(),
            'max_minutes' => $turnaroundTimes->max(),
            'total_samples' => $items->count(),
        ];
    }
}