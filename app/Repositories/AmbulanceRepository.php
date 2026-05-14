<?php

namespace App\Repositories;

use App\Models\Ambulance;
use App\Models\Staff;
use App\Repositories\Interfaces\AmbulanceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AmbulanceRepository implements AmbulanceRepositoryInterface
{
    private function baseLoads(): array
    {
        return ['facility', 'crewTeamLead', 'createdBy', 'updatedBy'];
    }

    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Ambulance::query()->with($this->baseLoads())->whereNull('deleted_at');
        $this->applyFilters($query, $filters);
        return $query->paginate($perPage);
    }

    public function getById(int $id): Ambulance
    {
        return Ambulance::with($this->baseLoads())->findOrFail($id);
    }

    public function getByUuid(string $uuid): Ambulance
    {
        return Ambulance::with($this->baseLoads())->where('ambulance_uuid', $uuid)->firstOrFail();
    }

    public function create(array $data): Ambulance
    {
        if (!isset($data['created_by_staff_id'])) {
            $data['created_by_staff_id'] = Auth::id()
                ? Staff::where('user_id', Auth::id())->value('id')
                : 1;
        }
        return Ambulance::create($data);
    }

    public function update(int $id, array $data): Ambulance
    {
        $ambulance = Ambulance::findOrFail($id);
        if (!isset($data['updated_by_staff_id'])) {
            $data['updated_by_staff_id'] = Auth::id()
                ? Staff::where('user_id', Auth::id())->value('id')
                : 1;
        }
        $ambulance->update($data);
        return $ambulance->fresh()->load($this->baseLoads());
    }

    public function delete(int $id): bool
    {
        return Ambulance::findOrFail($id)->delete();
    }

    public function getAvailable(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Ambulance::query()->with($this->baseLoads())->whereNull('deleted_at')->where('status', 'available');
        $this->applyFilters($query, $filters);
        return $query->paginate($perPage);
    }

    public function getByFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Ambulance::query()->with($this->baseLoads())->whereNull('deleted_at')->where('facility_id', $facilityId);
        $this->applyFilters($query, $filters);
        return $query->paginate($perPage);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['status', 'vehicle_type', 'equipment_level'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('vehicle_identifier', 'like', "%{$s}%")
                  ->orWhere('features', 'like', "%{$s}%");
            });
        }
    }
}
