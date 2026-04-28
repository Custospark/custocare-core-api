<?php

declare(strict_types=1);

namespace App\Repositories\Lab;

use App\Models\LabTest;
use App\Repositories\Lab\Contracts\LabTestRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LabTestRepository implements LabTestRepositoryInterface
{
    /**
     * @var LabTest
     */
    protected LabTest $model;

    /**
     * Constructor.
     *
     * @param LabTest $model
     */
    public function __construct(LabTest $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?LabTest
    {
        return $this->model->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findByUuid(string $uuid): ?LabTest
    {
        return $this->model->where('test_uuid', $uuid)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function findByCode(string $code, ?int $facilityId = null): ?LabTest
    {
        $query = $this->model->where('code', $code);

        if ($facilityId) {
            $query->byFacility($facilityId);
        }

        return $query->first();
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

        if (!empty($filters['template_id'])) {
            $query->where('template_id', $filters['template_id']);
        }

        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }

        if (isset($filters['is_active'])) {
            $filters['is_active'] ? $query->active() : $query->where('is_active', false);
        }

        if (isset($filters['requires_fasting'])) {
            $filters['requires_fasting'] ? $query->requiresFasting() : $query->where('requires_fasting', false);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('code', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Apply sorting
        $orderBy = $filters['order_by'] ?? 'name';
        $orderDirection = $filters['order_direction'] ?? 'asc';
        $query->orderBy($orderBy, $orderDirection);

        return $query->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(array $filters = []): Collection
    {
        $query = $this->model->query();

        if (!empty($filters['facility_id'])) {
            $query->byFacility($filters['facility_id']);
        }

        if (!empty($filters['template_id'])) {
            $query->where('template_id', $filters['template_id']);
        }

        if (isset($filters['is_active'])) {
            $filters['is_active'] ? $query->active() : $query->where('is_active', false);
        }

        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByTemplate(int $templateId, array $filters = []): Collection
    {
        $query = $this->model->where('template_id', $templateId);

        if (isset($filters['is_active'])) {
            $filters['is_active'] ? $query->active() : $query->where('is_active', false);
        }

        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByFacility(int $facilityId, array $filters = []): Collection
    {
        $query = $this->model->byFacility($facilityId);

        if (isset($filters['is_active'])) {
            $filters['is_active'] ? $query->active() : $query->where('is_active', false);
        }

        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveTests(?int $facilityId = null): Collection
    {
        $query = $this->model->active();

        if ($facilityId) {
            $query->byFacility($facilityId);
        }

        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByCategory(string $category, ?int $facilityId = null): Collection
    {
        $query = $this->model->byCategory($category)->active();

        if ($facilityId) {
            $query->byFacility($facilityId);
        }

        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getTestsRequiringFasting(?int $facilityId = null): Collection
    {
        $query = $this->model->newQuery()
            ->where('requires_fasting', true);

        if ($facilityId) {
            $query->byFacility($facilityId);
        }

        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): LabTest
    {
        return DB::transaction(function () use ($data) {
            return $this->model->create($data);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(LabTest $test, array $data): bool
    {
        return DB::transaction(function () use ($test, $data) {
            return $test->update($data);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function delete(LabTest $test): bool
    {
        return DB::transaction(function () use ($test) {
            return $test->delete();
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
    public function activate(LabTest $test): bool
    {
        return DB::transaction(function () use ($test) {
            return $test->activate();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function deactivate(LabTest $test): bool
    {
        return DB::transaction(function () use ($test) {
            return $test->deactivate();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function existsByName(string $name, ?int $facilityId = null, ?int $excludeId = null): bool
    {
        $query = $this->model->where('name', $name);

        if ($facilityId) {
            $query->byFacility($facilityId);
        }

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * {@inheritdoc}
     */
    public function getWithTemplate(int $id): ?LabTest
    {
        return $this->model->with('template')->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function getTestStatistics(int $testId): array
    {
        $test = $this->findById($testId);
        
        if (!$test) {
            return [];
        }

        $totalRequests = $test->requestItems()->count();
        $completedRequests = $test->requestItems()->where('status', 'verified')->count();
        $abnormalResults = $test->requestItems()
            ->whereHas('results', function ($query) {
                $query->whereIn('flag', ['abnormal', 'high', 'low', 'critical']);
            })->count();

        return [
            'total_requests' => $totalRequests,
            'completed_requests' => $completedRequests,
            'completion_rate' => $totalRequests > 0 ? round(($completedRequests / $totalRequests) * 100, 2) : 0,
            'abnormal_results_count' => $abnormalResults,
            'abnormal_rate' => $totalRequests > 0 ? round(($abnormalResults / $totalRequests) * 100, 2) : 0,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getPopularTests(int $facilityId, int $limit = 10): Collection
    {
        return $this->model
            ->select('lab_tests.*', DB::raw('COUNT(lab_request_items.id) as request_count'))
            ->leftJoin('lab_request_items', 'lab_tests.id', '=', 'lab_request_items.lab_test_id')
            ->where(function ($query) use ($facilityId) {
                $query->where('lab_tests.facility_id', $facilityId)
                      ->orWhere('lab_tests.is_shared', true);
            })
            ->groupBy('lab_tests.id')
            ->orderBy('request_count', 'desc')
            ->limit($limit)
            ->get();
    }
}