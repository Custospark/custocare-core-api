<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LearningMaterialResource;
use App\Models\LearningMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Authenticated users: browse published learning materials (Custocare Hub).
 */
class LearningMaterialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string', Rule::in(LearningMaterial::allowedCategories())],
        ]);

        $query = LearningMaterial::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id');

        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
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

    public function show(Request $request, string $uuid): JsonResponse
    {
        $material = LearningMaterial::query()
            ->where('uuid', $uuid)
            ->where('is_published', true)
            ->first();

        if (! $material) {
            return response()->json([
                'success' => false,
                'message' => 'Learning material not found.',
                'data'    => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Learning material retrieved successfully',
            'data'    => (new LearningMaterialResource($material))->toArray($request),
        ]);
    }
}
