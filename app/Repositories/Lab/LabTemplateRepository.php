<?php

declare(strict_types=1);

namespace App\Repositories\Lab;

use App\Models\LabTemplate;
use App\Repositories\Lab\Contracts\LabTemplateRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LabTemplateRepository implements LabTemplateRepositoryInterface
{
    /**
     * @var LabTemplate
     */
    protected LabTemplate $model;

    /**
     * Constructor.
     *
     * @param LabTemplate $model
     */
    public function __construct(LabTemplate $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?LabTemplate
    {
        return $this->model->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findByUuid(string $uuid): ?LabTemplate
    {
        return $this->model->where('template_uuid', $uuid)->first();
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

        if (!empty($filters['structure_type'])) {
            $query->ofType($filters['structure_type']);
        }

        if (isset($filters['is_active'])) {
            $filters['is_active'] ? $query->active() : $query->where('is_active', false);
        }

        if (!empty($filters['is_shared'])) {
            $filters['is_shared'] === 'true' ? $query->shared() : $query->where('is_shared', false);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Apply sorting
        $orderBy = $filters['order_by'] ?? 'created_at';
        $orderDirection = $filters['order_direction'] ?? 'desc';
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

        if (!empty($filters['structure_type'])) {
            $query->ofType($filters['structure_type']);
        }

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

        if (!empty($filters['structure_type'])) {
            $query->ofType($filters['structure_type']);
        }

        if (isset($filters['is_active'])) {
            $filters['is_active'] ? $query->active() : $query->where('is_active', false);
        }

        return $query->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveTemplates(?int $facilityId = null): Collection
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
    public function getSharedTemplates(): Collection
    {
        return $this->model->shared()->active()->get();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): LabTemplate
    {
        return DB::transaction(function () use ($data) {
            return $this->model->create($data);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(LabTemplate $template, array $data): bool
    {
        return DB::transaction(function () use ($template, $data) {
            return $template->update($data);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function delete(LabTemplate $template): bool
    {
        return DB::transaction(function () use ($template) {
            return $template->delete();
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
    public function activate(LabTemplate $template): bool
    {
        return DB::transaction(function () use ($template) {
            return $template->activate();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function deactivate(LabTemplate $template): bool
    {
        return DB::transaction(function () use ($template) {
            return $template->deactivate();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function existsByName(string $name, ?int $facilityId = null, ?int $excludeId = null): bool
    {
        $query = $this->model->where('name', $name);

        if ($facilityId) {
            $query->where(function ($q) use ($facilityId) {
                $q->where('facility_id', $facilityId)
                  ->orWhere('is_shared', true);
            });
        }

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * {@inheritdoc}
     */
    public function getWithRelations(int $id): ?LabTemplate
    {
        return $this->model->with(['tests' => function ($query) {
                $query->active();
            }, 'fields' => function ($query) {
                $query->active()->ordered();
            }])->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function getByStructureType(string $structureType, ?int $facilityId = null): Collection
    {
        $query = $this->model->ofType($structureType)->active();

        if ($facilityId) {
            $query->byFacility($facilityId);
        }

        return $query->get();
    }
}