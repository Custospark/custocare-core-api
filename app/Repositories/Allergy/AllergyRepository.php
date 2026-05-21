<?php
// app/Repositories/AllergyRepository.php

namespace App\Repositories\Allergy;

use App\Models\Allergy;
use App\Repositories\Contracts\AllergyRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AllergyRepository implements AllergyRepositoryInterface
{
    public function find(int $id): ?Allergy
    {
        return Allergy::with(['patient.user', 'recordedBy', 'visit.facility'])->find($id);
    }

    public function findAllForPatient(int $patientId): Collection
    {
        return Allergy::with(['patient.user', 'recordedBy', 'visit.facility'])
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findAllForVisit(int $visitId): Collection
    {
        return Allergy::with(['patient.user', 'recordedBy', 'visit.facility'])
            ->where('visit_id', $visitId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getActiveForPatient(int $patientId): Collection
    {
        return Allergy::with(['patient.user', 'recordedBy', 'visit.facility'])
            ->where('patient_id', $patientId)
            ->where('is_active', true)
            ->whereNull('resolved_at')
            ->orderBy('severity', 'desc')
            ->get();
    }

    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return Allergy::with(['patient.user', 'recordedBy', 'visit.facility'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function create(array $data): Allergy
    {
        return DB::transaction(function () use ($data) {
            $allergy = Allergy::create($data);
            Log::info('Allergy created', ['allergy_id' => $allergy->id, 'patient_id' => $data['patient_id']]);
            return $allergy;
        });
    }

    public function update(Allergy $allergy, array $data): bool
    {
        return DB::transaction(function () use ($allergy, $data) {
            $result = $allergy->update($data);
            Log::info('Allergy updated', ['allergy_id' => $allergy->id]);
            return $result;
        });
    }

    public function delete(Allergy $allergy): bool
    {
        return DB::transaction(function () use ($allergy) {
            $result = $allergy->delete();
            Log::info('Allergy soft deleted', ['allergy_id' => $allergy->id]);
            return $result;
        });
    }

    public function restore(Allergy $allergy): bool
    {
        return DB::transaction(function () use ($allergy) {
            $result = $allergy->restore();
            Log::info('Allergy restored', ['allergy_id' => $allergy->id]);
            return $result;
        });
    }

    public function forceDelete(Allergy $allergy): bool
    {
        return DB::transaction(function () use ($allergy) {
            $result = $allergy->forceDelete();
            Log::warning('Allergy permanently deleted', ['allergy_id' => $allergy->id]);
            return $result;
        });
    }
}