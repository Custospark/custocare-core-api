<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\StoreLabTemplateFieldRequest;
use App\Http\Requests\Lab\UpdateLabTemplateFieldRequest;
use App\Http\Resources\Lab\LabTemplateFieldResource;
use App\Http\Resources\Lab\LabTemplateFieldCollection;
use App\Services\Lab\Contracts\LabTemplateFieldServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LabTemplateFieldController extends Controller
{
    /**
     * @var LabTemplateFieldServiceInterface
     */
    protected LabTemplateFieldServiceInterface $fieldService;

    /**
     * Constructor.
     *
     * @param LabTemplateFieldServiceInterface $fieldService
     */
    public function __construct(LabTemplateFieldServiceInterface $fieldService)
    {
        $this->fieldService = $fieldService;
    }

    /**
     * Display a listing of template fields.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'template_id', 'data_type', 'is_active', 'is_required', 'is_critical', 'search',
                'order_by', 'order_direction', 'per_page'
            ]);
            
            $perPage = $request->get('per_page', 20);
            $result = $this->fieldService->getAllFields($filters, $perPage);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $fields = new LabTemplateFieldCollection($result['data']['fields']);
            $result['data']['fields'] = $fields;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve template fields', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve template fields',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created template field.
     *
     * @param StoreLabTemplateFieldRequest $request
     * @return JsonResponse
     */
    public function store(StoreLabTemplateFieldRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->fieldService->createField($validatedData);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_CREATED 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['field'] = new LabTemplateFieldResource($result['data']['field']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to create template field', [
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create template field',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified template field.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $result = $this->fieldService->getFieldByUuid($uuid);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Field not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $result['data']['field'] = new LabTemplateFieldResource($result['data']['field']);
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve template field', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve template field',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified template field.
     *
     * @param UpdateLabTemplateFieldRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdateLabTemplateFieldRequest $request, string $uuid): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->fieldService->updateField($uuid, $validatedData);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : ($result['message'] === 'Field not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST);
            
            if ($result['success']) {
                $result['data']['field'] = new LabTemplateFieldResource($result['data']['field']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to update template field', [
                'uuid' => $uuid,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update template field',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified template field.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $result = $this->fieldService->deleteField($uuid);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : ($result['message'] === 'Field not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST);
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to delete template field', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete template field',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Activate the specified template field.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function activate(string $uuid): JsonResponse
    {
        try {
            $result = $this->fieldService->activateField($uuid);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['field'] = new LabTemplateFieldResource($result['data']['field']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to activate template field', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate template field',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Deactivate the specified template field.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function deactivate(string $uuid): JsonResponse
    {
        try {
            $result = $this->fieldService->deactivateField($uuid);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['field'] = new LabTemplateFieldResource($result['data']['field']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to deactivate template field', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate template field',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get fields by template.
     *
     * @param Request $request
     * @param string $templateUuid
     * @return JsonResponse
     */
    public function byTemplate(Request $request, string $templateUuid): JsonResponse
    {
        try {
            $filters = $request->only(['is_active', 'is_required']);
            $result = $this->fieldService->getFieldsByTemplate($templateUuid, $filters);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Template not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $fields = LabTemplateFieldResource::collection($result['data']['fields']);
            $result['data']['fields'] = $fields;
            Log::info("Here");
            Log::info($result);
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve fields by template', [
                'template_uuid' => $templateUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve fields',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get active fields by template.
     *
     * @param string $templateUuid
     * @return JsonResponse
     */
    public function activeByTemplate(string $templateUuid): JsonResponse
    {
        try {
            $result = $this->fieldService->getActiveFieldsByTemplate($templateUuid);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Template not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $fields = LabTemplateFieldResource::collection($result['data']['fields']);
            $result['data']['fields'] = $fields;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve active fields by template', [
                'template_uuid' => $templateUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active fields',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get required fields by template.
     *
     * @param string $templateUuid
     * @return JsonResponse
     */
    public function requiredByTemplate(string $templateUuid): JsonResponse
    {
        try {
            $result = $this->fieldService->getRequiredFieldsByTemplate($templateUuid);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Template not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $fields = LabTemplateFieldResource::collection($result['data']['fields']);
            $result['data']['fields'] = $fields;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve required fields by template', [
                'template_uuid' => $templateUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve required fields',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get critical fields by template.
     *
     * @param string $templateUuid
     * @return JsonResponse
     */
    public function criticalByTemplate(string $templateUuid): JsonResponse
    {
        try {
            $result = $this->fieldService->getCriticalFieldsByTemplate($templateUuid);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Template not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $fields = LabTemplateFieldResource::collection($result['data']['fields']);
            $result['data']['fields'] = $fields;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve critical fields by template', [
                'template_uuid' => $templateUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve critical fields',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Bulk create fields for template.
     *
     * @param Request $request
     * @param string $templateUuid
     * @return JsonResponse
     */
    public function bulkStore(Request $request, string $templateUuid): JsonResponse
    {
        try {
            $request->validate([
                'fields' => 'required|array|min:1',
                'fields.*.name' => 'required|string|max:150',
                'fields.*.code' => 'nullable|string|max:50',
                'fields.*.data_type' => 'required|in:number,text,boolean,select',
                'fields.*.unit' => 'nullable|string|max:50',
                'fields.*.reference_min' => 'nullable|numeric',
                'fields.*.reference_max' => 'nullable|numeric',
                'fields.*.display_order' => 'nullable|integer|min:0',
                'fields.*.is_required' => 'boolean',
                'fields.*.is_active' => 'boolean',
                'fields.*.is_critical' => 'boolean',
                'fields.*.clinical_notes' => 'nullable|string',
            ]);
            
            $result = $this->fieldService->bulkCreateFields($templateUuid, $request->fields);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_CREATED 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $fields = LabTemplateFieldResource::collection($result['data']['fields']);
                $result['data']['fields'] = $fields;
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
            Log::error('Failed to bulk create fields', [
                'template_uuid' => $templateUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create fields',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Bulk update field display orders.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkUpdateOrders(Request $request): JsonResponse
    {
        Log::info($request);
        try {
            $request->validate([
                'orders' => 'required|array|min:1',
                'orders.*.field_uuid' => 'required|uuid|exists:lab_template_fields,field_uuid',
                'orders.*.display_order' => 'required|integer|min:0'
            ]);
            
            // Convert to format expected by service
            $orders = [];
            foreach ($request->orders as $order) {
                $orders[$order['field_uuid']] = $order['display_order'];
            }
            
            $result = $this->fieldService->bulkUpdateDisplayOrders($orders);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            return response()->json($result, $statusCode);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => []
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to bulk update display orders', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update display orders',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Duplicate fields from one template to another.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function duplicate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'source_template_uuid' => 'required|uuid|exists:lab_templates,template_uuid',
                'target_template_uuid' => 'required|uuid|exists:lab_templates,template_uuid'
            ]);
            
            $result = $this->fieldService->duplicateFields(
                $request->source_template_uuid,
                $request->target_template_uuid
            );
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_CREATED 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $fields = LabTemplateFieldResource::collection($result['data']['fields']);
                $result['data']['fields'] = $fields;
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
            Log::error('Failed to duplicate fields', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate fields',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validate a field value.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function validateValue(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'value' => 'nullable'
            ]);
            
            $result = $this->fieldService->validateFieldValue($uuid, $request->value);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Field not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => []
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to validate field value', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate value',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}