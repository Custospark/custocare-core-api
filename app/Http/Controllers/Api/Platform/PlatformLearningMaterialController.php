<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\LearningMaterialResource;
use App\Models\LearningMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Platform super-admin: CRUD for hub learning materials (videos + imagery + copy).
 */
class PlatformLearningMaterialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category'      => ['nullable', 'string', Rule::in(LearningMaterial::allowedCategories())],
            'is_published'    => 'nullable|boolean',
            'include_trash' => 'nullable|boolean',
        ]);

        $query = LearningMaterial::query()->orderBy('sort_order')->orderBy('id');

        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        if (array_key_exists('is_published', $validated) && $validated['is_published'] !== null) {
            $query->where('is_published', (bool) $validated['is_published']);
        }

        if (! empty($validated['include_trash'])) {
            $query->withTrashed();
        }

        $items = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Learning materials retrieved successfully',
            'data'    => $items
                ->map(fn (LearningMaterial $m) => (new LearningMaterialResource($m))->toArray($request))
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedPayload($request);

        $material = new LearningMaterial($data);
        $material->created_by = $request->user()?->id;
        $material->save();

        return response()->json([
            'success' => true,
            'message' => 'Learning material created successfully',
            'data'    => (new LearningMaterialResource($material->fresh()))->toArray($request),
        ], 201);
    }

    public function update(Request $request, LearningMaterial $learningMaterial): JsonResponse
    {
        $data = $this->validatedPayload($request, isUpdate: true);
        $learningMaterial->fill($data);
        $learningMaterial->save();

        return response()->json([
            'success' => true,
            'message' => 'Learning material updated successfully',
            'data'    => (new LearningMaterialResource($learningMaterial->fresh()))->toArray($request),
        ]);
    }

    public function destroy(LearningMaterial $learningMaterial): JsonResponse
    {
        $learningMaterial->delete();

        return response()->json([
            'success' => true,
            'message' => 'Learning material archived successfully',
            'data'    => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'title'            => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:50000'],
            'video_url'        => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:2048', 'url'],
            'thumbnail_url'    => ['nullable', 'string', 'max:2048', 'url'],
            'banner_image_url' => ['nullable', 'string', 'max:2048', 'url'],
            'category'         => [$isUpdate ? 'sometimes' : 'required', 'string', Rule::in(LearningMaterial::allowedCategories())],
            'sort_order'       => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_published'     => ['nullable', 'boolean'],
        ];

        $validated = $request->validate($rules);

        if (array_key_exists('is_published', $validated)) {
            $validated['is_published'] = (bool) $validated['is_published'];
        }

        if (array_key_exists('sort_order', $validated)) {
            $validated['sort_order'] = (int) $validated['sort_order'];
        }

        return $validated;
    }
}
