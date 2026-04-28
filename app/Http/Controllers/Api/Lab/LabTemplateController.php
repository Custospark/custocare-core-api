<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\StoreLabTemplateRequest;
use App\Http\Requests\Lab\UpdateLabTemplateRequest;
use App\Http\Resources\Lab\LabTemplateResource;
use App\Http\Resources\Lab\LabTemplateCollection;
use App\Services\Lab\Contracts\LabTemplateServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LabTemplateController extends Controller
{
    /**
     * @var LabTemplateServiceInterface
     */
    protected LabTemplateServiceInterface $templateService;

    /**
     * Constructor.
     *
     * @param LabTemplateServiceInterface $templateService
     */
    public function __construct(LabTemplateServiceInterface $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Display a listing of lab templates.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'facility_id', 'structure_type', 'is_active', 'is_shared', 'search',
                'order_by', 'order_direction', 'per_page'
            ]);
            
            $perPage = $request->get('per_page', 20);
            $result = $this->templateService->getAllTemplates($filters, $perPage);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $templates = new LabTemplateCollection($result['data']['templates']);
            $result['data']['templates'] = $templates;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve lab templates', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lab templates',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created lab template.
     *
     * @param StoreLabTemplateRequest $request
     * @return JsonResponse
     */
    public function store(StoreLabTemplateRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->templateService->createTemplate($validatedData);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_CREATED 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['template'] = new LabTemplateResource($result['data']['template']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to create lab template', [
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create lab template',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified lab template.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $result = $this->templateService->getTemplateByUuid($uuid);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Template not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $result['data']['template'] = new LabTemplateResource($result['data']['template']);
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve lab template', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lab template',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified lab template.
     *
     * @param UpdateLabTemplateRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdateLabTemplateRequest $request, string $uuid): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->templateService->updateTemplate($uuid, $validatedData);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : ($result['message'] === 'Template not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST);
            
            if ($result['success']) {
                $result['data']['template'] = new LabTemplateResource($result['data']['template']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to update lab template', [
                'uuid' => $uuid,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update lab template',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified lab template.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $result = $this->templateService->deleteTemplate($uuid);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : ($result['message'] === 'Template not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST);
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to delete lab template', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete lab template',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Activate the specified lab template.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function activate(string $uuid): JsonResponse
    {
        try {
            $result = $this->templateService->activateTemplate($uuid);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['template'] = new LabTemplateResource($result['data']['template']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to activate lab template', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate lab template',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Deactivate the specified lab template.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function deactivate(string $uuid): JsonResponse
    {
        try {
            $result = $this->templateService->deactivateTemplate($uuid);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['template'] = new LabTemplateResource($result['data']['template']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to deactivate lab template', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate lab template',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get active templates.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function active(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            $result = $this->templateService->getActiveTemplates($facilityId);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $templates = LabTemplateResource::collection($result['data']['templates']);
            $result['data']['templates'] = $templates;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve active templates', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active templates',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get shared templates.
     *
     * @return JsonResponse
     */
    public function shared(): JsonResponse
    {
        try {
            $result = $this->templateService->getSharedTemplates();
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $templates = LabTemplateResource::collection($result['data']['templates']);
            $result['data']['templates'] = $templates;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve shared templates', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve shared templates',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get template with tests and fields.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function withRelations(string $uuid): JsonResponse
    {
        try {
            $result = $this->templateService->getTemplateWithRelations($uuid);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Template not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $result['data']['template'] = new LabTemplateResource($result['data']['template']);
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve template with relations', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve template details',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Copy template to facility.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function copyToFacility(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'facility_id' => 'required|exists:facilities,id'
            ]);
            
            $result = $this->templateService->copyTemplateToFacility($uuid, $request->facility_id);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_CREATED 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['template'] = new LabTemplateResource($result['data']['template']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => []
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to copy template to facility', [
                'uuid' => $uuid,
                'facility_id' => $request->facility_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to copy template',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}