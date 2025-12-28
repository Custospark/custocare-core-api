<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DataResidencyRule\StoreDataResidencyRuleRequest;
use App\Http\Requests\DataResidencyRule\UpdateDataResidencyRuleRequest;
use App\Http\Resources\DataResidencyRuleResource;
use App\Services\Contracts\DataResidencyRuleServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DataResidencyRuleController extends Controller
{
    /**
     * The data residency rule service instance.
     *
     * @var DataResidencyRuleServiceInterface
     */
    protected DataResidencyRuleServiceInterface $service;

    /**
     * Constructor with dependency injection.
     *
     * @param DataResidencyRuleServiceInterface $service
     */
    public function __construct(DataResidencyRuleServiceInterface $service)
    {
        $this->service = $service;
        
        // Apply middleware
        // $this->middleware('auth:api');
        // $this->middleware('permission:view data residency rules')->only(['index', 'show']);
        // $this->middleware('permission:create data residency rules')->only(['store']);
        // $this->middleware('permission:update data residency rules')->only(['update']);
        // $this->middleware('permission:delete data residency rules')->only(['destroy']);
    }

    /**
     * Display a listing of the data residency rules.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'region_code',
                'region_name',
                'data_category',
                'status',
                'effective_from',
                'effective_to',
                'active_only'
            ]);
            
            $sort = [];
            if ($request->has('sort_by')) {
                $sort = [
                    'field' => $request->input('sort_by'),
                    'direction' => $request->input('sort_dir', 'asc')
                ];
            }
            
            $perPage = $request->input('per_page', 20);
            
            $result = $this->service->getAllRules($filters, $sort, $perPage);
            
            if (!$result['success']) {
                return $this->errorResponse(
                    $result['message'],
                    $result['error_code'] ?? 'FETCH_ERROR',
                    400
                );
            }
            
            return DataResidencyRuleResource::collection($result['data']['rules'])
                ->additional([
                    'success' => true,
                    'message' => $result['message'],
                    'pagination' => $result['data']['pagination']
                ])
                ->response();
                
        } catch (\Exception $e) {
            Log::error('Failed to fetch data residency rules', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve data residency rules. Please try again later.',
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Store a newly created data residency rule.
     *
     * @param StoreDataResidencyRuleRequest $request
     * @return JsonResponse
     */
    public function store(StoreDataResidencyRuleRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            // Add authenticated user ID if not provided
            if (!isset($validatedData['created_by_staff_id'])) {
                $validatedData['created_by_staff_id'] = $request->user()->id;
            }
            
            $result = $this->service->createRule($validatedData);
            
            if (!$result['success']) {
                return $this->errorResponse(
                    $result['message'],
                    $result['error_code'] ?? 'CREATION_ERROR',
                    400
                );
            }
            
            return (new DataResidencyRuleResource($result['data']))
                ->additional([
                    'success' => true,
                    'message' => $result['message']
                ])
                ->response()
                ->setStatusCode(201);
                
        } catch (\Exception $e) {
            Log::error('Failed to create data residency rule', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to create data residency rule. Please try again later.',
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Display the specified data residency rule.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $result = $this->service->getRuleById($id);
            
            if (!$result['success']) {
                return $this->errorResponse(
                    $result['message'],
                    $result['error_code'] ?? 'NOT_FOUND',
                    404
                );
            }
            
            return (new DataResidencyRuleResource($result['data']))
                ->additional([
                    'success' => true,
                    'message' => $result['message']
                ])
                ->response();
                
        } catch (\Exception $e) {
            Log::error('Failed to fetch data residency rule', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve data residency rule. Please try again later.',
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Update the specified data residency rule.
     *
     * @param UpdateDataResidencyRuleRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateDataResidencyRuleRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $result = $this->service->updateRule($id, $validatedData);
            
            if (!$result['success']) {
                return $this->errorResponse(
                    $result['message'],
                    $result['error_code'] ?? 'UPDATE_ERROR',
                    400
                );
            }
            
            return (new DataResidencyRuleResource($result['data']))
                ->additional([
                    'success' => true,
                    'message' => $result['message']
                ])
                ->response();
                
        } catch (\Exception $e) {
            Log::error('Failed to update data residency rule', [
                'id' => $id,
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to update data residency rule. Please try again later.',
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Remove the specified data residency rule.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->service->deleteRule($id);
            
            if (!$result['success']) {
                return $this->errorResponse(
                    $result['message'],
                    $result['error_code'] ?? 'DELETE_ERROR',
                    400
                );
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => null,
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'deleted_id' => $id
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to delete data residency rule', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to delete data residency rule. Please try again later.',
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Validate data processing against residency rules.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validateProcessing(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'data_category' => 'required|string|in:clinical_records,financial_data,identity_information,audit_logs,research_data,genomic_data',
                'processing_region' => 'required|string|max:10',
                'storage_region' => 'required|string|max:10',
            ]);
            
            $result = $this->service->validateDataProcessing(
                $request->input('data_category'),
                $request->input('processing_region'),
                $request->input('storage_region')
            );
            
            $statusCode = $result['success'] ? 200 : 400;
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'] ?? null,
                'error_code' => $result['error_code'] ?? null,
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'validation_type' => 'data_processing'
                ]
            ], $statusCode);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                'VALIDATION_ERROR',
                422,
                $e->errors()
            );
        } catch (\Exception $e) {
            Log::error('Failed to validate data processing', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to validate data processing. Please try again later.',
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Validate cross-border data transfer.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validateCrossBorderTransfer(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'source_region' => 'required|string|max:10',
                'target_region' => 'required|string|max:10',
                'data_category' => 'required|string|in:clinical_records,financial_data,identity_information,audit_logs,research_data,genomic_data',
            ]);
            
            $result = $this->service->validateCrossBorderTransfer(
                $request->input('source_region'),
                $request->input('target_region'),
                $request->input('data_category')
            );
            
            $statusCode = $result['success'] ? 200 : 400;
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'] ?? null,
                'error_code' => $result['error_code'] ?? null,
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'validation_type' => 'cross_border_transfer'
                ]
            ], $statusCode);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                'VALIDATION_ERROR',
                422,
                $e->errors()
            );
        } catch (\Exception $e) {
            Log::error('Failed to validate cross-border transfer', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to validate cross-border transfer. Please try again later.',
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Get applicable rules for a data category and region.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getApplicableRules(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'data_category' => 'required|string|in:clinical_records,financial_data,identity_information,audit_logs,research_data,genomic_data',
                'region_code' => 'required|string|max:10',
            ]);
            
            $result = $this->service->getApplicableRules(
                $request->input('data_category'),
                $request->input('region_code')
            );
            
            if (!$result['success']) {
                return $this->errorResponse(
                    $result['message'],
                    $result['error_code'] ?? 'FETCH_ERROR',
                    400
                );
            }
            
            return DataResidencyRuleResource::collection($result['data']['rules'])
                ->additional([
                    'success' => true,
                    'message' => $result['message'],
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'data_category' => $request->input('data_category'),
                        'region_code' => $request->input('region_code'),
                        'count' => $result['data']['count']
                    ]
                ])
                ->response();
                
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                'VALIDATION_ERROR',
                422,
                $e->errors()
            );
        } catch (\Exception $e) {
            Log::error('Failed to get applicable rules', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve applicable rules. Please try again later.',
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Get summary of data residency rules.
     *
     * @return JsonResponse
     */
    public function summary(): JsonResponse
    {
        try {
            $result = $this->service->getRulesSummary();
            
            if (!$result['success']) {
                return $this->errorResponse(
                    $result['message'],
                    $result['error_code'] ?? 'SUMMARY_ERROR',
                    400
                );
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'summary_type' => 'data_residency_rules'
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get rules summary', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve rules summary. Please try again later.',
                'SERVER_ERROR',
                500
            );
        }
    }

    /**
     * Create a standardized error response.
     *
     * @param string $message
     * @param string $errorCode
     * @param int $statusCode
     * @param array|null $errors
     * @return JsonResponse
     */
    private function errorResponse(string $message, string $errorCode, int $statusCode, ?array $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode,
            'data' => null,
            'meta' => [
                'timestamp' => now()->toISOString()
            ]
        ];
        
        if ($errors) {
            $response['errors'] = $errors;
        }
        
        return response()->json($response, $statusCode);
    }
}