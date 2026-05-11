<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\LearningMaterialResource;
use App\Models\LearningMaterial;
use App\Services\Learning\VideoThumbnailResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function previewThumbnail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'video_url' => ['required', 'string', 'max:2048', 'url'],
        ]);

        $resolved = VideoThumbnailResolver::resolve($data['video_url']);

        return response()->json([
            'success' => true,
            'message' => $resolved
                ? 'Preview thumbnail resolved for this video URL.'
                : 'No automatic preview is available for this host (try uploading an image).',
            'data'    => [
                'thumbnail_url' => $resolved,
            ],
        ]);
    }

    /**
     * Same pattern as profile photos: store on the public disk, return relative path + asset URL.
     */
    public function uploadThumbnailForMaterial(Request $request, LearningMaterial $learningMaterial): JsonResponse
    {
        return $this->respondWithStoredThumbnail($request, $learningMaterial);
    }

    public function uploadThumbnailPending(Request $request): JsonResponse
    {
        return $this->respondWithStoredThumbnail($request, null);
    }

    private function respondWithStoredThumbnail(Request $request, ?LearningMaterial $learningMaterial): JsonResponse
    {
        $request->validate([
            'photo'                     => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:2048'],
            'previous_thumbnail_path'   => ['nullable', 'string', 'max:512'],
        ]);

        $file = $request->file('photo');

        if ($learningMaterial !== null) {
            $disk = Storage::disk('public');
            $materialDir = 'learning-material-thumbnails/'.$learningMaterial->id;
            $previousPath = $learningMaterial->thumbnail_path;

            // Wipe the whole material folder so repeat uploads (before save) cannot leave duplicates.
            if ($disk->exists($materialDir)) {
                $disk->deleteDirectory($materialDir);
            }

            // Legacy / pending paths stored on the row but outside this folder (e.g. pending/… before first save).
            if ($previousPath && $disk->exists($previousPath)) {
                $disk->delete($previousPath);
            }
        } else {
            $this->deletePendingThumbnailIfAllowed($request->string('previous_thumbnail_path')->toString());
        }

        $directory = $learningMaterial !== null
            ? 'learning-material-thumbnails/'.$learningMaterial->id
            : 'learning-material-thumbnails/pending/'.Str::ulid();

        $path = $file->store($directory, 'public');

        return response()->json([
            'success' => true,
            'message' => 'Thumbnail uploaded successfully.',
            'data'    => [
                'thumbnail_path' => $path,
                'thumbnail_url'  => asset('storage/'.$path),
            ],
        ], 200);
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

        $previousPath = $learningMaterial->thumbnail_path;
        $learningMaterial->fill($data);

        if ($previousPath && $previousPath !== $learningMaterial->thumbnail_path) {
            Storage::disk('public')->delete($previousPath);
        }

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
            'thumbnail_path'   => ['nullable', 'string', 'max:512'],
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

        foreach (['thumbnail_path', 'thumbnail_url', 'banner_image_url'] as $key) {
            if (array_key_exists($key, $validated) && $validated[$key] === '') {
                $validated[$key] = null;
            }
        }

        return $validated;
    }

    /**
     * Remove a prior pending upload when the client replaces the thumbnail before save (no traversal).
     */
    private function deletePendingThumbnailIfAllowed(string $path): void
    {
        if ($path === '' || str_contains($path, '..')) {
            return;
        }

        $prefix = 'learning-material-thumbnails/pending/';
        if (! str_starts_with($path, $prefix)) {
            return;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
