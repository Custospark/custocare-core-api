<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\StoreLabTestRequest;
use App\Http\Requests\Lab\UpdateLabTestRequest;
use App\Http\Resources\Lab\LabTestResource;
use App\Http\Resources\Lab\LabTestCollection;
use App\Services\Lab\Contracts\LabTestServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LabTestController extends Controller
{
    /**
     * @var LabTestServiceInterface
     */
    protected LabTestServiceInterface $testService;

    /**
     * Constructor.
     *
     * @param LabTestServiceInterface $testService
     */
    public function __construct(LabTestServiceInterface $testService)
    {
        $this->testService = $testService;
    }

    /**
     * Display a listing of lab tests.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'facility_id', 'template_id', 'category', 'is_active', 
                'requires_fasting', 'search', 'order_by', 'order_direction', 'per_page'
            ]);
            
            $perPage = $request->get('per_page', 20);
            $result = $this->testService->getAllTests($filters, $perPage);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $tests = new LabTestCollection($result['data']['tests']);
            $result['data']['tests'] = $tests;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve lab tests', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lab tests',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created lab test.
     *
     * @param StoreLabTestRequest $request
     * @return JsonResponse
     */
    public function store(StoreLabTestRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->testService->createTest($validatedData);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_CREATED 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['test'] = new LabTestResource($result['data']['test']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to create lab test', [
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create lab test',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified lab test.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $result = $this->testService->getTestByUuid($uuid);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Test not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $result['data']['test'] = new LabTestResource($result['data']['test']);
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve lab test', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lab test',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified lab test.
     *
     * @param UpdateLabTestRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdateLabTestRequest $request, string $uuid): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->testService->updateTest($uuid, $validatedData);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : ($result['message'] === 'Test not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST);
            
            if ($result['success']) {
                $result['data']['test'] = new LabTestResource($result['data']['test']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to update lab test', [
                'uuid' => $uuid,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update lab test',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified lab test.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $result = $this->testService->deleteTest($uuid);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : ($result['message'] === 'Test not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST);
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to delete lab test', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete lab test',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Activate the specified lab test.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function activate(string $uuid): JsonResponse
    {
        try {
            $result = $this->testService->activateTest($uuid);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['test'] = new LabTestResource($result['data']['test']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to activate lab test', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate lab test',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Deactivate the specified lab test.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function deactivate(string $uuid): JsonResponse
    {
        try {
            $result = $this->testService->deactivateTest($uuid);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['test'] = new LabTestResource($result['data']['test']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to deactivate lab test', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate lab test',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get tests by template.
     *
     * @param Request $request
     * @param string $templateUuid
     * @return JsonResponse
     */
    public function byTemplate(Request $request, string $templateUuid): JsonResponse
    {
        try {
            $filters = $request->only(['is_active']);
            $result = $this->testService->getTestsByTemplate($templateUuid, $filters);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Template not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $tests = LabTestResource::collection($result['data']['tests']);
            $result['data']['tests'] = $tests;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve tests by template', [
                'template_uuid' => $templateUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tests',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get tests by category.
     *
     * @param Request $request
     * @param string $category
     * @return JsonResponse
     */
    public function byCategory(Request $request, string $category): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            $result = $this->testService->getTestsByCategory($category, $facilityId);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $tests = LabTestResource::collection($result['data']['tests']);
            $result['data']['tests'] = $tests;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve tests by category', [
                'category' => $category,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tests',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get tests requiring fasting.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function fasting(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            $result = $this->testService->getTestsRequiringFasting($facilityId);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $tests = LabTestResource::collection($result['data']['tests']);
            $result['data']['tests'] = $tests;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve tests requiring fasting', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tests',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get test statistics.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function statistics(string $uuid): JsonResponse
    {
        try {
            $result = $this->testService->getTestStatistics($uuid);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Test not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve test statistics', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve test statistics',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get popular tests.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function popular(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'facility_id' => 'required|exists:facilities,id',
                'limit' => 'nullable|integer|min:1|max:100'
            ]);
            
            $limit = $request->get('limit', 10);
            $result = $this->testService->getPopularTests($request->facility_id, $limit);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $tests = LabTestResource::collection($result['data']['tests']);
            $result['data']['tests'] = $tests;
            
            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => []
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve popular tests', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve popular tests',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}