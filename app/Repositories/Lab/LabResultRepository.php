<?php

declare(strict_types=1);

namespace App\Repositories\Lab;

use App\Models\LabResult;
use App\Repositories\Lab\Contracts\LabResultRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LabResultRepository implements LabResultRepositoryInterface
{
    /**
     * @var LabResult
     */
    protected LabResult $model;

    /**
     * Constructor.
     *
     * @param LabResult $model
     */
    public function __construct(LabResult $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?LabResult
    {
        return $this->model->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findByUuid(string $uuid): ?LabResult
    {
        return $this->model->where('result_uuid', $uuid)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->query();

        if (!empty($filters['lab_request_item_id'])) {
            $query->where('lab_request_item_id', $filters['lab_request_item_id']);
        }

        if (!empty($filters['template_field_id'])) {
            $query->where('template_field_id', $filters['template_field_id']);
        }

        if (!empty($filters['flag'])) {
            $query->where('flag', $filters['flag']);
        }

        if (isset($filters['is_abnormal_flagged'])) {
            $filters['is_abnormal_flagged'] ? $query->abnormalFlagged() : $query->where('is_abnormal_flagged', false);
        }

        if (isset($filters['is_verified'])) {
            $filters['is_verified'] ? $query->verified() : $query->unverified();
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('recorded_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('recorded_at', '<=', $filters['date_to']);
        }

        $orderBy = $filters['order_by'] ?? 'recorded_at';
        $orderDirection = $filters['order_direction'] ?? 'desc';
        $query->orderBy($orderBy, $orderDirection);

        return $query->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getByLabRequestItem(int $labRequestItemId): Collection
    {
        return $this->model->where('lab_request_item_id', $labRequestItemId)->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByTemplateField(int $templateFieldId, array $filters = []): Collection
    {
        $query = $this->model->where('template_field_id', $templateFieldId);

        if (!empty($filters['flag'])) {
            $query->where('flag', $filters['flag']);
        }

        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByFlag(string $flag, ?int $facilityId = null): Collection
    {
        $query = $this->model->where('flag', $flag);
        
        if ($facilityId) {
            $query->whereHas('labRequestItem.labRequest', function ($q) use ($facilityId) {
                $q->byFacility($facilityId);
            });
        }
        
        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getAbnormalResults(?int $facilityId = null): Collection
    {
        $query = $this->model->abnormal();
        
        if ($facilityId) {
            $query->whereHas('labRequestItem.labRequest', function ($q) use ($facilityId) {
                $q->byFacility($facilityId);
            });
        }
        
        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getCriticalResults(?int $facilityId = null): Collection
    {
        $query = $this->model->critical();
        
        if ($facilityId) {
            $query->whereHas('labRequestItem.labRequest', function ($q) use ($facilityId) {
                $q->byFacility($facilityId);
            });
        }
        
        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getPendingResults(?int $facilityId = null): Collection
    {
        $query = $this->model->pending();
        
        if ($facilityId) {
            $query->whereHas('labRequestItem.labRequest', function ($q) use ($facilityId) {
                $q->byFacility($facilityId);
            });
        }
        
        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getUnverifiedResults(?int $facilityId = null): Collection
    {
        $query = $this->model->unverified();
        
        if ($facilityId) {
            $query->whereHas('labRequestItem.labRequest', function ($q) use ($facilityId) {
                $q->byFacility($facilityId);
            });
        }
        
        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): LabResult
    {
        return DB::transaction(function () use ($data) {
            $result = $this->model->create($data);
            
            // Update the result flag based on value
            $result->updateFlagFromValue();
            
            // Update the parent item's result flag
            if ($result->labRequestItem) {
                $result->labRequestItem->updateResultFlagFromResults();
            }
            
            return $result->fresh();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function bulkCreate(int $labRequestItemId, array $results): Collection
    {
        return DB::transaction(function () use ($labRequestItemId, $results) {
            $createdResults = [];
            
            foreach ($results as $resultData) {
                $resultData['lab_request_item_id'] = $labRequestItemId;
                $result = $this->model->create($resultData);
                $result->updateFlagFromValue();
                $createdResults[] = $result;
            }
            
            // Update the parent item's result flag
            $item = $createdResults[0]->labRequestItem ?? null;
            if ($item) {
                $item->updateResultFlagFromResults();
            }
            
            return new Collection($createdResults);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(LabResult $result, array $data): bool
    {
        return DB::transaction(function () use ($result, $data) {
            $updated = $result->update($data);
            
            if ($updated && isset($data['value'])) {
                $result->updateFlagFromValue();
                
                // Update the parent item's result flag
                if ($result->labRequestItem) {
                    $result->labRequestItem->updateResultFlagFromResults();
                }
            }
            
            return $updated;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function delete(LabResult $result): bool
    {
        return DB::transaction(function () use ($result) {
            $deleted = $result->delete();
            
            // Update the parent item's result flag
            if ($result->labRequestItem) {
                $result->labRequestItem->updateResultFlagFromResults();
            }
            
            return $deleted;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function restore(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $result = $this->model->withTrashed()->find($id);
            $restored = $result?->restore() ?? false;
            
            if ($restored && $result->labRequestItem) {
                $result->labRequestItem->updateResultFlagFromResults();
            }
            
            return $restored;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function verify(LabResult $result, int $verifiedByStaffId): bool
    {
        return DB::transaction(function () use ($result, $verifiedByStaffId) {
            return $result->verify($verifiedByStaffId);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function updateFlagFromValue(LabResult $result): bool
    {
        return DB::transaction(function () use ($result) {
            return $result->updateFlagFromValue();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function markCriticalAlertSent(LabResult $result): bool
    {
        return DB::transaction(function () use ($result) {
            return $result->markCriticalAlertSent();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getWithRelations(int $id): ?LabResult
    {
        return $this->model->with([
            'labRequestItem',
            'templateField',
            'recordedBy',
            'verifiedBy'
        ])->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function getByDateRange(string $startDate, string $endDate, ?int $facilityId = null): Collection
    {
        $query = $this->model->whereBetween('recorded_at', [$startDate, $endDate]);
        
        if ($facilityId) {
            $query->whereHas('labRequestItem.labRequest', function ($q) use ($facilityId) {
                $q->byFacility($facilityId);
            });
        }
        
        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByPatient(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->whereHas('labRequestItem.labRequest', function ($q) use ($patientId) {
            $q->where('patient_id', $patientId);
        });

        if (!empty($filters['flag'])) {
            $query->where('flag', $filters['flag']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('recorded_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('recorded_at', '<=', $filters['date_to']);
        }

        $orderBy = $filters['order_by'] ?? 'recorded_at';
        $orderDirection = $filters['order_direction'] ?? 'desc';
        $query->orderBy($orderBy, $orderDirection);

        return $query->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getResultStatistics(int $facilityId, string $startDate, string $endDate): array
    {
        $results = $this->model->whereHas('labRequestItem.labRequest', function ($q) use ($facilityId) {
                $q->byFacility($facilityId);
            })
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->get();

        $total = $results->count();
        $normal = $results->where('flag', 'normal')->count();
        $abnormal = $results->whereIn('flag', ['abnormal', 'high', 'low'])->count();
        $critical = $results->where('flag', 'critical')->count();
        $verified = $results->whereNotNull('verified_at')->count();

        return [
            'total_results' => $total,
            'normal_count' => $normal,
            'abnormal_count' => $abnormal,
            'critical_count' => $critical,
            'verified_count' => $verified,
            'normal_rate' => $total > 0 ? round(($normal / $total) * 100, 2) : 0,
            'abnormal_rate' => $total > 0 ? round(($abnormal / $total) * 100, 2) : 0,
            'critical_rate' => $total > 0 ? round(($critical / $total) * 100, 2) : 0,
            'verification_rate' => $total > 0 ? round(($verified / $total) * 100, 2) : 0,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getResultTrends(int $labTestId, int $patientId, int $limit = 10): Collection
    {
        return $this->model->whereHas('labRequestItem', function ($q) use ($labTestId) {
                $q->where('lab_test_id', $labTestId);
            })
            ->whereHas('labRequestItem.labRequest', function ($q) use ($patientId) {
                $q->where('patient_id', $patientId);
            })
            ->whereIn('flag', ['normal', 'low', 'high', 'critical'])
            ->orderBy('recorded_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getCriticalResultsRequiringAttention(int $facilityId): Collection
    {
        return $this->model->critical()
            ->where('is_critical_alert_sent', false)
            ->whereHas('labRequestItem.labRequest', function ($q) use ($facilityId) {
                $q->byFacility($facilityId);
            })
            ->orderBy('recorded_at', 'asc')
            ->get();
    }
}