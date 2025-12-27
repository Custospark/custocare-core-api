<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PatientConsent\StorePatientConsentRequest;
use App\Http\Requests\PatientConsent\UpdatePatientConsentRequest;
use App\Http\Resources\PatientConsentResource;
use App\Services\Contracts\PatientConsentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class PatientConsentController extends Controller
{
    /**
     * Service instance.
     *
     * @var PatientConsentServiceInterface
     */
    protected PatientConsentServiceInterface $service;

    /**
     * Create a new controller instance.
     *
     * @param PatientConsentServiceInterface $service
     */
    public function __construct(PatientConsentServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of patient consents.
     *
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function index(Request $request, int $patientId): JsonResponse
    {
        try {
            // Authorize using policy
            $this->authorize('viewAny', [\App\Models\PatientConsent::class, $patientId]);

            $filters = $request->only([
                'consent_type', 'status', 'from_date', 'to_date',
                'search', 'order_by', 'order_direction', 'per_page'
            ]);

            $result = $this->service->getPatientConsents($patientId, $filters);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], $result['status']);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => PatientConsentResource::collection($result['data']),
                'meta' => [
                    'current_page' => $result['data']->currentPage(),
                    'last_page' => $result['data']->lastPage(),
                    'per_page' => $result['data']->perPage(),
                    'total' => $result['data']->total(),
                ]
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Unauthorized access to patient consents', [
                'patient_id' => $patientId,
                'user_id' => auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view these consents.'
            ], 403);
        } catch (\Exception $e) {
            Log::error('Error in PatientConsentController@index', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.'
            ], 500);
        }
    }

    /**
     * Store a newly created consent.
     *
     * @param StorePatientConsentRequest $request
     * @return JsonResponse
     */
    public function store(StorePatientConsentRequest $request): JsonResponse
    {
        try {
            // Authorize using policy
            $this->authorize('create', \App\Models\PatientConsent::class);

            $validated = $request->validated();
            $result = $this->service->createConsent($validated);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'] ?? null,
                    'data' => isset($result['data']) ? new PatientConsentResource($result['data']) : null
                ], $result['status']);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new PatientConsentResource($result['data'])
            ], $result['status']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Unauthorized consent creation attempt', [
                'user_id' => auth::id(),
                'input' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to create consents.'
            ], 403);
        } catch (\Exception $e) {
            Log::error('Error in PatientConsentController@store', [
                'input' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while creating consent.'
            ], 500);
        }
    }

    /**
     * Display the specified consent.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $result = $this->service->getConsentByUuid($uuid);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], $result['status']);
            }

            // Authorize using policy
            $this->authorize('view', $result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new PatientConsentResource($result['data']->loadMissing(['patient', 'witness', 'revoker', 'legalGuardian', 'supersededBy']))
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Unauthorized consent view attempt', [
                'consent_uuid' => $uuid,
                'user_id' => auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this consent.'
            ], 403);
        } catch (\Exception $e) {
            Log::error('Error in PatientConsentController@show', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving consent.'
            ], 500);
        }
    }

    /**
     * Update the specified consent.
     *
     * @param UpdatePatientConsentRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdatePatientConsentRequest $request, string $uuid): JsonResponse
    {
        try {
            $result = $this->service->getConsentByUuid($uuid);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], $result['status']);
            }

            // Authorize using policy
            $this->authorize('update', $result['data']);

            $validated = $request->validated();
            $updateResult = $this->service->updateConsent($uuid, $validated);

            if (!$updateResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $updateResult['message'],
                    'errors' => $updateResult['errors'] ?? null,
                    'data' => isset($updateResult['data']) ? new PatientConsentResource($updateResult['data']) : null
                ], $updateResult['status']);
            }

            return response()->json([
                'success' => true,
                'message' => $updateResult['message'],
                'data' => new PatientConsentResource($updateResult['data'])
            ], $updateResult['status']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Unauthorized consent update attempt', [
                'consent_uuid' => $uuid,
                'user_id' => auth::id(),
                'input' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this consent.'
            ], 403);
        } catch (\Exception $e) {
            Log::error('Error in PatientConsentController@update', [
                'uuid' => $uuid,
                'input' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while updating consent.'
            ], 500);
        }
    }

    /**
     * Revoke the specified consent.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function revoke(Request $request, string $uuid): JsonResponse
    {
        try {
            $result = $this->service->getConsentByUuid($uuid);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], $result['status']);
            }

            // Authorize using policy
            $this->authorize('revoke', $result['data']);

            $validated = $request->validate([
                'revocation_reason' => 'required|string|max:500',
                'revoked_by_staff_id' => 'required|exists:staff,id',
            ]);

            $revokeResult = $this->service->revokeConsent($uuid, $validated);

            if (!$revokeResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $revokeResult['message'],
                    'errors' => $revokeResult['errors'] ?? null,
                    'data' => isset($revokeResult['data']) ? new PatientConsentResource($revokeResult['data']) : null
                ], $revokeResult['status']);
            }

            return response()->json([
                'success' => true,
                'message' => $revokeResult['message'],
                'data' => new PatientConsentResource($revokeResult['data'])
            ], $revokeResult['status']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Unauthorized consent revocation attempt', [
                'consent_uuid' => $uuid,
                'user_id' => auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to revoke this consent.'
            ], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in PatientConsentController@revoke', [
                'uuid' => $uuid,
                'input' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while revoking consent.'
            ], 500);
        }
    }

    /**
     * Validate consent for specific action.
     *
     * @param Request $request
     * @param int $patientId
     * @param string $consentType
     * @return JsonResponse
     */
    public function validateConsent(Request $request, int $patientId, string $consentType): JsonResponse
    {
        try {
            // Authorize using policy
            $this->authorize('validate', [\App\Models\PatientConsent::class, $patientId]);

            $scopeCheck = $request->only(['facility_id', 'department_id', 'staff_id', 'service_category']);
            $result = $this->service->validateConsent($patientId, $consentType, $scopeCheck);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'valid' => $result['valid'],
                    'consent' => $result['consent'] ? new PatientConsentResource($result['consent']) : null
                ], $result['status']);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'valid' => $result['valid'],
                'consent' => new PatientConsentResource($result['consent'])
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Unauthorized consent validation attempt', [
                'patient_id' => $patientId,
                'consent_type' => $consentType,
                'user_id' => auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to validate consents.'
            ], 403);
        } catch (\Exception $e) {
            Log::error('Error in PatientConsentController@validateConsent', [
                'patient_id' => $patientId,
                'consent_type' => $consentType,
                'scope_check' => $scopeCheck,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while validating consent.'
            ], 500);
        }
    }

    /**
     * Get consent statistics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            // Authorize using policy
            $this->authorize('viewStatistics', \App\Models\PatientConsent::class);

            $filters = $request->only(['patient_id', 'from_date', 'to_date']);
            $result = $this->service->getStatistics($filters);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], $result['status']);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data']
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Unauthorized statistics access attempt', [
                'user_id' => auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view consent statistics.'
            ], 403);
        } catch (\Exception $e) {
            Log::error('Error in PatientConsentController@statistics', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving statistics.'
            ], 500);
        }
    }

    /**
     * Get expiring consents.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function expiring(Request $request): JsonResponse
    {
        try {
            // Authorize using policy
            $this->authorize('viewExpiring', \App\Models\PatientConsent::class);

            $daysThreshold = $request->get('days_threshold', 30);
            $result = $this->service->getExpiringConsents($daysThreshold);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], $result['status']);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => PatientConsentResource::collection($result['data']),
                'count' => $result['count']
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Unauthorized expiring consents access attempt', [
                'user_id' => auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view expiring consents.'
            ], 403);
        } catch (\Exception $e) {
            Log::error('Error in PatientConsentController@expiring', [
                'days_threshold' => $daysThreshold,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving expiring consents.'
            ], 500);
        }
    }

    /**
     * Get consent types and legal basis options.
     *
     * @return JsonResponse
     */
    public function options(): JsonResponse
    {
        try {
            // This endpoint is generally accessible
            $consentTypes = $this->service->getConsentTypes();
            $legalBasisOptions = $this->service->getLegalBasisOptions();

            return response()->json([
                'success' => true,
                'data' => [
                    'consent_types' => $consentTypes,
                    'legal_basis_options' => $legalBasisOptions,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in PatientConsentController@options', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving options.'
            ], 500);
        }
    }
}