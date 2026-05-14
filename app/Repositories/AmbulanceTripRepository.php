<?php

namespace App\Repositories;

use App\Models\AmbulanceTrip;
use App\Models\Staff;
use App\Repositories\Interfaces\AmbulanceTripRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AmbulanceTripRepository implements AmbulanceTripRepositoryInterface
{
    private function baseLoads(): array
    {
        return [
            'facility', 'patient', 'visit', 'ambulance',
            'dispatchStaff', 'requestingStaff',
            'pickupFacility', 'destinationFacility',
            'createdBy', 'updatedBy',
        ];
    }

    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = AmbulanceTrip::query()->with($this->baseLoads())->whereNull('deleted_at');
        $this->applyFilters($query, $filters);
        return $query->paginate($perPage);
    }

    public function getById(int $id): AmbulanceTrip
    {
        return AmbulanceTrip::with($this->baseLoads())->findOrFail($id);
    }

    public function getByUuid(string $uuid): AmbulanceTrip
    {
        return AmbulanceTrip::with($this->baseLoads())->where('trip_uuid', $uuid)->firstOrFail();
    }

    public function create(array $data): AmbulanceTrip
    {
        if (!isset($data['created_by_staff_id'])) {
            $data['created_by_staff_id'] = Auth::id()
                ? Staff::where('user_id', Auth::id())->value('id')
                : 1;
        }
        return AmbulanceTrip::create($data);
    }

    public function update(int $id, array $data): AmbulanceTrip
    {
        $trip = AmbulanceTrip::findOrFail($id);
        if (!isset($data['updated_by_staff_id'])) {
            $data['updated_by_staff_id'] = Auth::id()
                ? Staff::where('user_id', Auth::id())->value('id')
                : 1;
        }
        $trip->update($data);
        return $trip->fresh()->load($this->baseLoads());
    }

    public function delete(int $id): bool
    {
        return AmbulanceTrip::findOrFail($id)->delete();
    }

    public function getActive(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = AmbulanceTrip::query()->with($this->baseLoads())->whereNull('deleted_at')->whereNotIn('status', ['completed', 'cancelled']);
        $this->applyFilters($query, $filters);
        return $query->paginate($perPage);
    }

    public function getByPatient(int $patientId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = AmbulanceTrip::query()->with($this->baseLoads())->whereNull('deleted_at')->where('patient_id', $patientId);
        $this->applyFilters($query, $filters);
        return $query->paginate($perPage);
    }

    public function getByFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = AmbulanceTrip::query()->with($this->baseLoads())->whereNull('deleted_at')
            ->where(function ($q) use ($facilityId) {
                $q->where('facility_id', $facilityId)
                  ->orWhere('pickup_facility_id', $facilityId)
                  ->orWhere('destination_facility_id', $facilityId);
            });
        $this->applyFilters($query, $filters);
        return $query->paginate($perPage);
    }

    public function getFromFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = AmbulanceTrip::query()->with($this->baseLoads())->whereNull('deleted_at')
            ->where(function ($q) use ($facilityId) {
                $q->where('facility_id', $facilityId)->orWhere('pickup_facility_id', $facilityId);
            });
        $this->applyFilters($query, $filters);
        return $query->paginate($perPage);
    }

    public function getToFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = AmbulanceTrip::query()->with($this->baseLoads())->whereNull('deleted_at')
            ->where('destination_facility_id', $facilityId);
        $this->applyFilters($query, $filters);
        return $query->paginate($perPage);
    }

    // ─── Status transitions ───

    public function dispatchTrip(int $id): AmbulanceTrip { return tap(AmbulanceTrip::findOrFail($id), fn($t) => $t->dispatch())->fresh()->load($this->baseLoads()); }
    public function markEnRoute(int $id): AmbulanceTrip { return tap(AmbulanceTrip::findOrFail($id), fn($t) => $t->markEnRoute())->fresh()->load($this->baseLoads()); }
    public function markOnScene(int $id): AmbulanceTrip { return tap(AmbulanceTrip::findOrFail($id), fn($t) => $t->markOnScene())->fresh()->load($this->baseLoads()); }
    public function markPatientContact(int $id): AmbulanceTrip { return tap(AmbulanceTrip::findOrFail($id), fn($t) => $t->markPatientContact())->fresh()->load($this->baseLoads()); }
    public function markDepartScene(int $id): AmbulanceTrip { return tap(AmbulanceTrip::findOrFail($id), fn($t) => $t->markDepartScene())->fresh()->load($this->baseLoads()); }
    public function markAtDestination(int $id): AmbulanceTrip { return tap(AmbulanceTrip::findOrFail($id), fn($t) => $t->markAtDestination())->fresh()->load($this->baseLoads()); }
    public function markCompleted(int $id): AmbulanceTrip { return tap(AmbulanceTrip::findOrFail($id), fn($t) => $t->markCompleted())->fresh()->load($this->baseLoads()); }
    public function cancelTrip(int $id, ?string $reason = null): AmbulanceTrip { return tap(AmbulanceTrip::findOrFail($id), fn($t) => $t->cancel($reason))->fresh()->load($this->baseLoads()); }

    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['status', 'trip_type', 'priority', 'patient_id', 'ambulance_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['from_date'])) { $query->whereDate('created_at', '>=', $filters['from_date']); }
        if (isset($filters['to_date'])) { $query->whereDate('created_at', '<=', $filters['to_date']); }
        if (isset($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('dispatch_notes', 'like', "%{$s}%")
                  ->orWhere('trip_notes', 'like', "%{$s}%")
                  ->orWhere('pickup_location', 'like', "%{$s}%")
                  ->orWhere('destination_location', 'like', "%{$s}%");
            });
        }
    }
}
