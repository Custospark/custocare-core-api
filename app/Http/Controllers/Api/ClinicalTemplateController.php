<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Template\StoreTemplateRequest;
use App\Http\Requests\Template\UpdateTemplateRequest;
use App\Http\Resources\ClinicalTemplateResource;
use App\Services\Prescription\ClinicalTemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ClinicalTemplateController extends Controller
{
    protected ClinicalTemplateService $templateService;

    public function __construct(ClinicalTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Get all templates
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['facility_id', 'category', 'is_active']);
        $templates = $this->templateService->getAllTemplates($filters);
        
        return response()->json([
            'success' => true,
            'message' => 'Templates retrieved successfully',
            'data' => ClinicalTemplateResource::collection($templates),
            'meta' => [
                'total' => $templates->count(),
                'categories' => $this->templateService->getTemplateCategories()
            ]
        ]);
    }

   /**
 * Get facility templates (for dropdown on prescription page)
 */
protected function castToBoolean($value, $default = true): bool
{
    if (is_null($value)) {
        return $default;
    }
    
    if (is_bool($value)) {
        return $value;
    }
    
    if (is_string($value)) {
        return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
    }
    
    return (bool) $value;
}

public function facilityTemplates(Request $request): JsonResponse
{
    // Cast BEFORE validation
    $includeSystem = $this->castToBoolean(
        $request->input('include_system'), 
        true
    );
    
    // Merge the casted value back into the request
    $request->merge([
        'include_system' => $includeSystem,
        'facility_id' => (int) $request->input('facility_id')
    ]);
    
    // Now validate with correct types
    $request->validate([
        'facility_id' => ['required', 'exists:facilities,id'],
        'include_system' => ['boolean']
    ]);
    
    $templates = $this->templateService->getFacilityTemplates(
        $request->input('facility_id'),
        $request->input('include_system')
    );
    
    return response()->json([
        'success' => true,
        'message' => 'Facility templates retrieved successfully',
        'data' => ClinicalTemplateResource::collection($templates),
        'meta' => [
            'facility_id' => $request->input('facility_id'),
            'include_system' => $request->input('include_system'),
            'total' => $templates->count()
        ]
    ]);
}

    /**
     * Get templates by category
     */
    public function byCategory(string $category, Request $request): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'exists:facilities,id']
        ]);
        
        $templates = $this->templateService->getTemplatesByCategory(
            $category,
            $request->input('facility_id')
        );
        
        return response()->json([
            'success' => true,
            'message' => "Templates in category '{$category}' retrieved successfully",
            'data' => ClinicalTemplateResource::collection($templates),
            'meta' => [
                'category' => $category,
                'total' => $templates->count()
            ]
        ]);
    }

    /**
     * Get single template
     */
    public function show(int $id): JsonResponse
    {
        $template = $this->templateService->getTemplate($id);
        
        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
                'data' => null
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Template retrieved successfully',
            'data' => new ClinicalTemplateResource($template)
        ]);
    }

    /**
     * Create new template
     */
    public function store(StoreTemplateRequest $request): JsonResponse
    {
        $userId = Auth::id();
        
        $result = $this->templateService->createTemplate($request->validated(), $userId);
        
        $statusCode = $result['success'] ? 201 : 400;
        
        return response()->json($result, $statusCode);
    }

    /**
     * Update template
     */
    public function update(UpdateTemplateRequest $request, int $id): JsonResponse
    {
        $userId = Auth::id();
        
        $result = $this->templateService->updateTemplate($id, $request->validated(), $userId);
        
        $statusCode = $result['success'] ? 200 : ($result['data'] === null ? 404 : 400);
        
        return response()->json($result, $statusCode);
    }

    /**
     * Delete template
     */
    public function destroy(int $id): JsonResponse
    {
        $result = $this->templateService->deleteTemplate($id);
        
        $statusCode = $result['success'] ? 200 : 404;
        
        return response()->json($result, $statusCode);
    }

    /**
     * Toggle template active status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $result = $this->templateService->toggleTemplateStatus($id);
        
        $statusCode = $result['success'] ? 200 : 404;
        
        return response()->json($result, $statusCode);
    }

    /**
     * Search templates
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'keyword' => ['required', 'string', 'min:2'],
            'facility_id' => ['required', 'exists:facilities,id']
        ]);
        
        $templates = $this->templateService->searchTemplates(
            $request->input('keyword'),
            $request->input('facility_id')
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Search completed successfully',
            'data' => ClinicalTemplateResource::collection($templates),
            'meta' => [
                'keyword' => $request->input('keyword'),
                'total' => $templates->count()
            ]
        ]);
    }

    /**
     * Get all template categories (for UI dropdown)
     */
    public function categories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Template categories retrieved successfully',
            'data' => $this->templateService->getTemplateCategories()
        ]);
    }
}