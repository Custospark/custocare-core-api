<?php
// app/Repositories/Contracts/AllergyRepositoryInterface.php

namespace App\Repositories\Contracts;

use App\Models\Allergy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface AllergyRepositoryInterface
{
    public function find(int $id): ?Allergy;
    public function findAllForPatient(int $patientId): Collection;
    public function getActiveForPatient(int $patientId): Collection;
    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator;
    public function create(array $data): Allergy;
    public function update(Allergy $allergy, array $data): bool;
    public function delete(Allergy $allergy): bool;
    public function restore(Allergy $allergy): bool;
    public function forceDelete(Allergy $allergy): bool;
}