<?php

declare(strict_types=1);

namespace App\Repositories\Lab;

use App\Models\LabRequest;
use App\Repositories\Lab\Contracts\LabRequestRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LabRequestRepository implements LabRequestRepositoryInterface
{
    /**
     * @var LabRequest
     */
    protected LabRequest $model;

    /**
     * Constructor.
     *
     * @param LabRequest $model
     */
    public function __construct(LabRequest $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?LabRequest
    {
        return $this->model->find($id);
    }

    /**
     * {@inheritdoc}
     */

   public function findByUuid(string $uuid): ?LabRequest
{
    // First, verify the record exists
    Log::info('🔍 Looking for request with UUID: ' . $uuid);
    
    // Get the request with relationships - EXCLUDING cancelled items
    $labRequest = $this->model
        ->where('request_uuid', $uuid)
        ->with([
            'items' => function ($query) {
                // Exclude cancelled items from the relationship
                $query->where('status', '!=', 'cancelled')
                    ->with([
                        'labTest',
                        'results' => function ($q) {
                            $q->orderBy('recorded_at', 'desc');
                        },
                        'collectedBy',
                        'startedBy',      // ← Add this
                        'completedBy',    // ← Add this
                        'verifiedBy',
                        'cancelledBy',    // ← Add this
                        'createdBy'       // ← Add this (for pending/created tracking)
                    ]);
            },
            'items.labTest',
            'items.results',
            'patient.user',
            'visit',
            'facility',
            'requestedBy.user',
            'reviewedBy.user'
        ])
        ->first();
    
    // Also load the count of cancelled items separately if needed
    if ($labRequest) {
        $cancelledCount = $this->model
            ->where('request_uuid', $uuid)
            ->first()
            ->items()
            ->where('status', 'cancelled')
            ->count();
        
        // Attach cancelled count to the request (optional)
        // $labRequest->cancelled_items_count = $cancelledCount;
    }
    
    return $labRequest;
}
    /**
     * {@inheritdoc}
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Apply filters
        if (!empty($filters['facility_id'])) {
            $query->byFacility($filters['facility_id']);
        }

        if (!empty($filters['patient_id'])) {
            $query->byPatient($filters['patient_id']);
        }

        if (!empty($filters['visit_id'])) {
            $query->where('visit_id', $filters['visit_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['requested_by_staff_id'])) {
            $query->where('requested_by_staff_id', $filters['requested_by_staff_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('requested_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('requested_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('clinical_notes', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('cancellation_reason', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Apply sorting
        $orderBy = $filters['order_by'] ?? 'requested_at';
        $orderDirection = $filters['order_direction'] ?? 'desc';
        $query->orderBy($orderBy, $orderDirection);

        return $query->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getByFacility(int $facilityId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $filters['facility_id'] = $facilityId;
        return $this->getAllPaginated($filters, $perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getByPatient(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $filters['patient_id'] = $patientId;
        return $this->getAllPaginated($filters, $perPage);
    }

    /**
     * {@inheritdoc}
     */
   /**
 * {@inheritdoc}
 */
    public function getByVisit(int $visitId): Collection
    {
        return $this->model->where('visit_id', $visitId)
            ->whereNotIn('status', ['cancelled']) // Add other statuses if needed
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByStatus(string $status, ?int $facilityId = null): Collection
    {
        $query = $this->model->where('status', $status);
        
        if ($facilityId) {
            $query->byFacility($facilityId);
        }
        
        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByPriority(string $priority, ?int $facilityId = null): Collection
    {
        $query = $this->model->where('priority', $priority);
        
        if ($facilityId) {
            $query->byFacility($facilityId);
        }
        
        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getPendingRequests(?int $facilityId = null): Collection
    {
        $query = $this->model->pending();
        
        if ($facilityId) {
            $query->byFacility($facilityId);
        }
        
        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getInProgressRequests(?int $facilityId = null): Collection
    {
        $query = $this->model->inProgress();
        
        if ($facilityId) {
            $query->byFacility($facilityId);
        }
        
        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByDateRange(string $startDate, string $endDate, ?int $facilityId = null): Collection
    {
        $query = $this->model->dateRange($startDate, $endDate);
        
        if ($facilityId) {
            $query->byFacility($facilityId);
        }
        
        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): LabRequest
    {
        return DB::transaction(function () use ($data) {
            return $this->model->create($data);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(LabRequest $request, array $data): bool
    {
        return DB::transaction(function () use ($request, $data) {
            return $request->update($data);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function delete(LabRequest $request): bool
    {
        return DB::transaction(function () use ($request) {
            return $request->delete();
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
     */
    public function updateStatus(LabRequest $request, string $status): bool
    {
        return DB::transaction(function () use ($request, $status) {
            $request->status = $status;
            return $request->save();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(LabRequest $request, string $reason, ?int $cancelledByStaffId = null): bool
    {
        return DB::transaction(function () use ($request, $reason, $cancelledByStaffId) {
            return $request->cancel($reason, $cancelledByStaffId);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getWithItems(int $id): ?LabRequest
    {
        return $this->model->with([
            'items' => function ($query) {
                $query->where('status', '!=', 'cancelled')
                    ->with(['labTest', 'results']);
            }
        ])->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function getWithFullDetails(int $id): ?LabRequest
    {
        return $this->model->with([
            'items' => function ($query) {
                $query->where('status', '!=', 'cancelled')
                    ->with(['labTest', 'results.templateField']);
            },
            'patient',
            'visit',
            'facility',
            'requestedBy',
            'reviewedBy'
        ])->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestStatistics(int $facilityId, string $startDate, string $endDate): array
    {
        $requests = $this->model->byFacility($facilityId)
            ->whereBetween('requested_at', [$startDate, $endDate])
            ->get();

        $total = $requests->count();
        $completed = $requests->where('status', 'completed')->count();
        $reviewed = $requests->where('status', 'reviewed')->count();
        $cancelled = $requests->where('status', 'cancelled')->count();
        $pending = $requests->where('status', 'pending')->count();
        $inProgress = $requests->where('status', 'in_progress')->count();

        $statPriority = [
            'routine' => $requests->where('priority', 'routine')->count(),
            'urgent' => $requests->where('priority', 'urgent')->count(),
            'stat' => $requests->where('priority', 'stat')->count(),
        ];

        return [
            'total_requests' => $total,
            'by_status' => [
                'pending' => $pending,
                'in_progress' => $inProgress,
                'completed' => $completed,
                'reviewed' => $reviewed,
                'cancelled' => $cancelled,
            ],
            'by_priority' => $statPriority,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'cancellation_rate' => $total > 0 ? round(($cancelled / $total) * 100, 2) : 0,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestsRequiringAttention(int $facilityId): Collection
    {
        return $this->model->byFacility($facilityId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->where('priority', 'stat')
            ->orderBy('requested_at', 'asc')
            ->get();
    }
}