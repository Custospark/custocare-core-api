<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PatientCreationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Services\Contracts\PatientServiceInterface;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class PatientController extends Controller
{
    /**
     * @var PatientServiceInterface
     */
    private $patientService;

    /**
     * Constructor.
     */
    public function __construct(PatientServiceInterface $patientService)
    {
        $this->patientService = $patientService;
        
        // Apply policy middleware
        // $this->authorizeResource(Patient::class, 'patient');
    }

    /**
     * Display a listing of patients.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $patients = $this->patientService->getAllPatients($perPage);
            
            return response()->json([
                'success' => true,
                'data' => PatientResource::collection($patients),
                'meta' => [
                    'current_page' => $patients->currentPage(),
                    'last_page' => $patients->lastPage(),
                    'per_page' => $patients->perPage(),
                    'total' => $patients->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve patients list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patients.',
                'errors' => config('app.debug') ? ['details' => $e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Store a newly created patient in storage.
     */
    public function store(StorePatientRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $patient = $this->patientService->createPatient($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Patient created successfully.',
                'data' => new PatientResource($patient),
            ], 201);

        } catch (PatientCreationException $e) {
            // Meaningful client response
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [],
                'data'=>[],
            ], $e->status);
        } catch (\Throwable $e) {
            // Unexpected errors
            Log::error('Unexpected error in patient store', [
                'data' => $request->except([
                    'medical_record_number_encrypted',
                    'primary_insurance_id_encrypted'
                ]),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create patient.',
                'errors' => ['server' => config('app.debug') ? $e->getMessage() : 'Internal server error'],
                 'data'=>[],
            ], 500);
        }
    }

    /**
     * Display the specified patient.
     */
    public function show(Patient $patient): JsonResponse
    {
        try {
            // Check if user can view sensitive data
            $includeSensitive = $this->patientService->getPatientByUuid($patient->patient_uuid);
            
            if (!$includeSensitive) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found.',
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => new PatientResource($patient->load(['user', 'primaryCareProvider', 'primaryCareFacility'])),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve patient', [
                'patient_uuid' => $patient->patient_uuid,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patient.',
            ], 500);
        }
    }

    /**
     * Update the specified patient in storage.
     */
    public function update(UpdatePatientRequest $request, Patient $patient): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $updated = $this->patientService->updatePatient($patient, $validatedData);
            
            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update patient.',
                ], 500);
            }
            
            // Refresh patient data
            $patient->refresh();
            
            return response()->json([
                'success' => true,
                'message' => 'Patient updated successfully.',
                'data' => new PatientResource($patient),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update patient', [
                'patient_uuid' => $patient->patient_uuid,
                'data' => $request->except(['medical_record_number_encrypted']),
                'error' => $e->getMessage(),
            ]);
            
            $statusCode = $e instanceof \Illuminate\Validation\ValidationException ? 422 : 500;
            $message = $e->getMessage();
            
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                $errors = $e->errors();
            } elseif (str_contains($message, 'cannot be updated')) {
                $statusCode = 400;
                $errors = ['status' => [$message]];
            } else {
                $errors = ['server' => config('app.debug') ? $message : 'Internal server error'];
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update patient.',
                'errors' => $errors,
            ], $statusCode);
        }
    }

    /**
     * Remove the specified patient from storage.
     */
    public function destroy(Patient $patient): JsonResponse
    {
        try {
            $deleted = $this->patientService->deletePatient($patient);
            
            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete patient.',
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Patient deleted successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete patient', [
                'patient_uuid' => $patient->patient_uuid,
                'error' => $e->getMessage(),
            ]);
            
            $statusCode = str_contains($e->getMessage(), 'cannot be deleted') ? 400 : 500;
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Restore a soft-deleted patient.
     */
    public function restore($uuid): JsonResponse
    {
        try {
            $patient = $this->patientService->getPatientByUuid($uuid);
            
            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found.',
                ], 404);
            }
            
            // Check authorization
            $this->authorize('restore', $patient);
            
            $restored = $this->patientService->restorePatient($patient);
            
            if (!$restored) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to restore patient.',
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Patient restored successfully.',
                'data' => new PatientResource($patient),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to restore patient', [
                'patient_uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore patient.',
            ], 500);
        }
    }

    /**
     * Update patient status.
     */
    public function updateStatus(Request $request, Patient $patient): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|in:active,inactive,deceased,merged,test_patient',
            ]);
            
            // Check authorization
            $this->authorize('update', $patient);
            
            if ($request->status === 'deceased') {
                $this->authorize('markDeceased', $patient);
            }
            
            $updated = $this->patientService->updatePatientStatus($patient, $request->status);
            
            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update patient status.',
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Patient status updated successfully.',
                'data' => new PatientResource($patient),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update patient status', [
                'patient_uuid' => $patient->patient_uuid,
                'status' => $request->status,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search patients.
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Patient::class);
            
            $criteria = $request->validate([
                'status' => 'nullable|in:active,inactive,deceased,merged,test_patient',
                'biological_sex' => 'nullable|in:male,female,intersex,unknown',
                'blood_type' => 'nullable|string|max:5',
                'requires_isolation' => 'nullable|boolean',
                'date_of_birth_from' => 'nullable|date',
                'date_of_birth_to' => 'nullable|date|after_or_equal:date_of_birth_from',
                'facility_id' => 'nullable|integer|exists:facilities,id',
            ]);
            
            $patients = $this->patientService->searchPatients($criteria);
            
            return response()->json([
                'success' => true,
                'data' => PatientResource::collection($patients),
                'meta' => [
                    'total' => $patients->count(),
                    'criteria' => $criteria,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to search patients', [
                'criteria' => $request->all(),
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to search patients.',
            ], 500);
        }
    }

    /**
     * Get patient statistics.
     */
    public function statistics(): JsonResponse
    {
        try {
            $this->authorize('viewAny', Patient::class);
            
            $stats = $this->patientService->getPatientStatistics();
            
            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get patient statistics', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics.',
            ], 500);
        }
    }

    /**
     * Export patient data.
     */
    public function export(Patient $patient): JsonResponse
    {
        try {
            $this->authorize('export', $patient);
            
            $data = $this->patientService->exportPatientData($patient);
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'exported_at' => now()->format('Y-m-d H:i:s'),
                    'consent_level' => $patient->default_consent_level,
                    'data_sharing_allowed' => $patient->data_sharing_allowed,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to export patient data', [
                'patient_uuid' => $patient->patient_uuid,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get patients by blood type.
     */
    public function byBloodType(string $bloodType): JsonResponse
    {
        try {
            $this->authorize('viewAny', Patient::class);
            
            $patients = $this->patientService->getPatientsByBloodType($bloodType);
            
            return response()->json([
                'success' => true,
                'data' => PatientResource::collection($patients),
                'meta' => [
                    'blood_type' => $bloodType,
                    'total' => $patients->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get patients by blood type', [
                'blood_type' => $bloodType,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patients by blood type.',
            ], 500);
        }
    }

    /**
     * Get patients requiring isolation.
     */
    public function requiringIsolation(): JsonResponse
    {
        try {
            $this->authorize('viewAny', Patient::class);
            
            $patients = $this->patientService->getPatientsRequiringIsolation();
            
            return response()->json([
                'success' => true,
                'data' => PatientResource::collection($patients),
                'meta' => [
                    'total' => $patients->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get patients requiring isolation', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patients requiring isolation.',
            ], 500);
        }
    }
}