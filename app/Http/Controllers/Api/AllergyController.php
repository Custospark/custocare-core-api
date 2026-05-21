<?php
// app/Http/Controllers/Api/AllergyController.php

namespace App\Http\Controllers\Api;

use App\Exceptions\AllergyCreationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Allergy\StoreAllergyRequest;
use App\Http\Requests\Allergy\UpdateAllergyRequest;
use App\Http\Resources\AllergyCollection;
use App\Http\Resources\AllergyResource;
use App\Models\Allergy;
use App\Models\Patient;
use App\Services\Contracts\AllergyServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AllergyController extends Controller
{
    private AllergyServiceInterface $allergyService;

    public function __construct(AllergyServiceInterface $allergyService)
    {
        $this->allergyService = $allergyService;
    }

    /**
     * Get all allergies for a patient.
     */
    public function index(Patient $patient): JsonResponse
    {
        try {
            $allergies = $this->allergyService->getAllergiesForPatient($patient);

            return response()->json([
                'success' => true,
                'data' => new AllergyCollection($allergies),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get patient allergies', [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve allergies.',
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get active allergies only (for warning display).
     */
    public function active(Patient $patient): JsonResponse
    {
        try {
            $allergies = $this->allergyService->getActiveAllergiesForPatient($patient);
            $warningText = $this->allergyService->getAllergyWarningText($patient);

            return response()->json([
                'success' => true,
                'data' => new AllergyCollection($allergies),
                'meta' => [
                    'warning_text' => $warningText,
                    'has_severe_allergy' => $this->allergyService->hasSevereAllergy($patient),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get active allergies', [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active allergies.',
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get allergies scoped to a specific visit.
     */
    public function visitAllergies(int $visitId): JsonResponse
    {
        try {
            $allergies = $this->allergyService->getAllergiesForVisit($visitId);

            return response()->json([
                'success' => true,
                'data' => new AllergyCollection($allergies),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get visit allergies', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve allergies.',
                'data' => [],
            ], 500);
        }
    }

    /**
     * Store a new allergy for a patient.
     */
    public function store(StoreAllergyRequest $request, Patient $patient): JsonResponse
    {
        try {
            $data = $request->validated();
            
            // Auto-set recorded_by to current user
            $data['recorded_by'] = Auth::id();

            $allergy = $this->allergyService->createAllergy($patient, $data);

            return response()->json([
                'success' => true,
                'message' => 'Allergy recorded successfully.',
                'data' => new AllergyResource($allergy->load(['recordedBy', 'visit'])),
            ], 201);

        } catch (AllergyCreationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], $e->status ?? 500);
        } catch (\Exception $e) {
            Log::error('Failed to create allergy', [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create allergy record.',
                'data' => [],
            ], 500);
        }
    }

    /**
     * Display a specific allergy.
     */
    public function show(Patient $patient, Allergy $allergy): JsonResponse
    {
        try {
            // Verify allergy belongs to patient
            if ($allergy->patient_id !== $patient->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Allergy does not belong to this patient.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new AllergyResource($allergy->load(['recordedBy', 'visit'])),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to show allergy', [
                'allergy_id' => $allergy->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve allergy.',
            ], 500);
        }
    }

    /**
     * Update an allergy.
     */
    public function update(UpdateAllergyRequest $request, Patient $patient, Allergy $allergy): JsonResponse
    {
        try {
            // Verify allergy belongs to patient
            if ($allergy->patient_id !== $patient->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Allergy does not belong to this patient.',
                ], 404);
            }

            $updated = $this->allergyService->updateAllergy($allergy, $request->validated());

            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update allergy.',
                ], 500);
            }

            $allergy->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Allergy updated successfully.',
                'data' => new AllergyResource($allergy),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update allergy', [
                'allergy_id' => $allergy->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update allergy.',
            ], 500);
        }
    }

    /**
     * Delete (soft delete) an allergy.
     */
    public function destroy(Patient $patient, Allergy $allergy): JsonResponse
    {
        try {
            // Verify allergy belongs to patient
            if ($allergy->patient_id !== $patient->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Allergy does not belong to this patient.',
                ], 404);
            }

            $deleted = $this->allergyService->deleteAllergy($allergy);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete allergy.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Allergy deleted successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete allergy', [
                'allergy_id' => $allergy->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete allergy.',
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted allergy.
     */
    public function restore(Patient $patient, $allergyId): JsonResponse
    {
        try {
            $allergy = Allergy::withTrashed()->findOrFail($allergyId);

            if ($allergy->patient_id !== $patient->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Allergy does not belong to this patient.',
                ], 404);
            }

            $restored = $this->allergyService->restoreAllergy($allergy);

            if (!$restored) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to restore allergy.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Allergy restored successfully.',
                'data' => new AllergyResource($allergy),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to restore allergy', [
                'allergy_id' => $allergyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore allergy.',
            ], 500);
        }
    }

    /**
     * Resolve an allergy (mark as resolved/inactive).
     */
    public function resolve(Patient $patient, Allergy $allergy): JsonResponse
    {
        try {
            if ($allergy->patient_id !== $patient->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Allergy does not belong to this patient.',
                ], 404);
            }

            if ($allergy->isResolved()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Allergy is already resolved.',
                ], 400);
            }

            $resolved = $this->allergyService->resolveAllergy($allergy);

            if (!$resolved) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to resolve allergy.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Allergy marked as resolved.',
                'data' => new AllergyResource($allergy->fresh()),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to resolve allergy', [
                'allergy_id' => $allergy->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve allergy.',
            ], 500);
        }
    }
}