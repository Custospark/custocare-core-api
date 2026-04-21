<?php

declare(strict_types=1);

namespace App\Repositories\Prescription;

use App\Models\ClinicalTemplate;
use App\Repositories\Contracts\ClinicalTemplateRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClinicalTemplateRepository implements ClinicalTemplateRepositoryInterface
{
    protected ClinicalTemplate $model;

    public function __construct(ClinicalTemplate $model)
    {
        $this->model = $model;
    }

    public function all(array $filters = []): Collection
    {
        $query = $this->model->query();
        
        if (!empty($filters['facility_id'])) {
            $query->byFacility($filters['facility_id']);
        }
        
        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }
        
        if (!empty($filters['is_active'])) {
            $query->active();
        }
        
        return $query->latest()->get();
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->query();
        
        if (!empty($filters['facility_id'])) {
            $query->byFacility($filters['facility_id']);
        }
        
        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }
        
        return $query->latest()->paginate($perPage);
    }

    public function find(int $id): ?object
    {
        return $this->model->find($id);
    }

    public function create(array $data): object
    {
        if (!isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']) . '-' . uniqid();
        }
        
        return DB::transaction(function () use ($data) {
            return $this->model->create($data);
        });
    }

    public function update(int $id, array $data): bool
    {
        $template = $this->model->find($id);
        
        if (!$template) {
            return false;
        }
        
        return DB::transaction(function () use ($template, $data) {
            return $template->update($data);
        });
    }

    public function delete(int $id): bool
    {
        $template = $this->model->find($id);
        
        if (!$template) {
            return false;
        }
        
        return $template->delete();
    }

    public function getByFacility(int $facilityId, bool $includeSystem = true): Collection
    {
        $query = $this->model->where('facility_id', $facilityId);
        
        if ($includeSystem) {
            $query->orWhere('visibility', 'System Wide (All Facilities)');
        }
        
        return $query->active()->orderBy('name')->get();
    }

    public function getByCategory(string $category, int $facilityId): Collection
    {
        return $this->model->where('category', $category)
                           ->where(function ($q) use ($facilityId) {
                               $q->where('facility_id', $facilityId)
                                 ->orWhere('visibility', 'System Wide (All Facilities)');
                           })
                           ->active()
                           ->get();
    }

    public function incrementUsage(int $id): bool
    {
        $template = $this->model->find($id);
        
        if (!$template) {
            return false;
        }
        
        return $template->increment('usage_count') ?? true;
    }

    public function toggleStatus(int $id): bool
    {
        $template = $this->model->find($id);
        
        if (!$template) {
            return false;
        }
        
        return $template->update(['is_active' => !$template->is_active]);
    }

    public function search(string $keyword, int $facilityId): Collection
    {
        return $this->model->where(function ($q) use ($keyword) {
                               $q->where('name', 'LIKE', "%{$keyword}%")
                                 ->orWhere('description', 'LIKE', "%{$keyword}%")
                                 ->orWhere('default_diagnosis', 'LIKE', "%{$keyword}%");
                           })
                           ->where(function ($q) use ($facilityId) {
                               $q->where('facility_id', $facilityId)
                                 ->orWhere('visibility', 'System Wide (All Facilities)');
                           })
                           ->active()
                           ->get();
    }
}