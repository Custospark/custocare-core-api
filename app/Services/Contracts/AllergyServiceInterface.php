<?php
// app/Services/Contracts/AllergyServiceInterface.php

namespace App\Services\Contracts;

use App\Models\Allergy;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface AllergyServiceInterface
{
    public function getAllergiesForPatient(Patient $patient): Collection;
    public function getActiveAllergiesForPatient(Patient $patient): Collection;
    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator;
    public function createAllergy(Patient $patient, array $data): Allergy;
    public function updateAllergy(Allergy $allergy, array $data): bool;
    public function deleteAllergy(Allergy $allergy): bool;
    public function restoreAllergy(Allergy $allergy): bool;
    public function resolveAllergy(Allergy $allergy): bool;
    public function hasSevereAllergy(Patient $patient, ?string $allergen = null): bool;
    public function getAllergyWarningText(Patient $patient): ?string;
}