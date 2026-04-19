<?php
// app/Services/Allergy/AllergyService.php

namespace App\Services\Allergy;

use App\Exceptions\AllergyCreationException;
use App\Models\Allergy;
use App\Models\Patient;
use App\Repositories\Contracts\AllergyRepositoryInterface;
use App\Services\Contracts\AllergyServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AllergyService implements AllergyServiceInterface
{
    private AllergyRepositoryInterface $allergyRepository;

    public function __construct(AllergyRepositoryInterface $allergyRepository)
    {
        $this->allergyRepository = $allergyRepository;
    }

    public function getAllergiesForPatient(Patient $patient): Collection
    {
        try {
            return $this->allergyRepository->findAllForPatient($patient->id);
        } catch (\Exception $e) {
            Log::error('Failed to get allergies for patient', [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    public function getActiveAllergiesForPatient(Patient $patient): Collection
    {
        try {
            return $this->allergyRepository->getActiveForPatient($patient->id);
        } catch (\Exception $e) {
            Log::error('Failed to get active allergies', [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->allergyRepository->getAllPaginated($perPage);
    }

    public function createAllergy(Patient $patient, array $data): Allergy
    {
        DB::beginTransaction();

        try {
            $validatedData = $this->validateAllergyData($data);

            $validatedData['patient_id'] = $patient->id;

            // If diagnosed_at not provided, use now()
            if (!isset($validatedData['diagnosed_at'])) {
                $validatedData['diagnosed_at'] = now();
            }

            $allergy = $this->allergyRepository->create($validatedData);

            DB::commit();

            Log::info('Allergy created successfully', [
                'patient_id' => $patient->id,
                'allergy_id' => $allergy->id,
                'allergen' => $allergy->allergen,
            ]);

            return $allergy;

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create allergy', [
                'patient_id' => $patient->id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            throw new AllergyCreationException(
                config('app.debug') ? $e->getMessage() : 'Failed to create allergy record',
                500
            );
        }
    }

    public function updateAllergy(Allergy $allergy, array $data): bool
    {
        DB::beginTransaction();

        try {
            $validatedData = $this->validateAllergyData($data, $allergy);

            $result = $this->allergyRepository->update($allergy, $validatedData);

            DB::commit();

            Log::info('Allergy updated successfully', [
                'allergy_id' => $allergy->id,
                'allergen' => $allergy->allergen,
            ]);

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update allergy', [
                'allergy_id' => $allergy->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function deleteAllergy(Allergy $allergy): bool
    {
        DB::beginTransaction();

        try {
            $result = $this->allergyRepository->delete($allergy);

            DB::commit();

            Log::info('Allergy deleted', [
                'allergy_id' => $allergy->id,
                'patient_id' => $allergy->patient_id,
            ]);

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete allergy', [
                'allergy_id' => $allergy->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function restoreAllergy(Allergy $allergy): bool
    {
        DB::beginTransaction();

        try {
            $result = $this->allergyRepository->restore($allergy);

            DB::commit();

            Log::info('Allergy restored', [
                'allergy_id' => $allergy->id,
            ]);

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore allergy', [
                'allergy_id' => $allergy->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function resolveAllergy(Allergy $allergy): bool
    {
        DB::beginTransaction();

        try {
            $result = $allergy->resolve();

            DB::commit();

            Log::info('Allergy resolved', [
                'allergy_id' => $allergy->id,
                'patient_id' => $allergy->patient_id,
            ]);

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to resolve allergy', [
                'allergy_id' => $allergy->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function hasSevereAllergy(Patient $patient, ?string $allergen = null): bool
    {
        $query = $this->allergyRepository->getActiveForPatient($patient->id)
            ->filter(fn($allergy) => $allergy->isSevere());

        if ($allergen) {
            $query = $query->filter(fn($allergy) => 
                stripos($allergy->allergen, $allergen) !== false
            );
        }

        return $query->isNotEmpty();
    }

    public function getAllergyWarningText(Patient $patient): ?string
    {
        $activeAllergies = $this->getActiveAllergiesForPatient($patient);

        if ($activeAllergies->isEmpty()) {
            return null;
        }

        $severeAllergies = $activeAllergies->filter(fn($a) => $a->isSevere());
        
        if ($severeAllergies->isNotEmpty()) {
            $allergens = $severeAllergies->pluck('allergen')->implode(', ');
            return "⚠️ SEVERE ALLERGY ALERT: {$allergens}";
        }

        $allergens = $activeAllergies->pluck('allergen')->implode(', ');
        return "⚠️ Allergy Alert: {$allergens}";
    }

    private function validateAllergyData(array $data, ?Allergy $allergy = null): array
    {
        $rules = [
            'allergen' => 'required|string|max:255',
            'reaction' => 'nullable|string|max:500',
            'severity' => 'required|in:mild,moderate,severe',
            'clinical_notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'diagnosed_at' => 'nullable|date',
            'resolved_at' => 'nullable|date|after_or_equal:diagnosed_at',
            'recorded_by' => 'nullable|exists:users,id',
            'visit_id' => 'nullable|exists:visits,id',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}