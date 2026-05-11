<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\HubCommunityPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * CRUD for Custocare Hub "Product updates" community posts ({@see HubCommunityPost::CHANNEL_PRODUCT_UPDATE}).
 * Visible to all authenticated users in the hub; authored only by platform administrators.
 */
class PlatformHubCommunityProductUpdateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'q' => ['sometimes', 'string', 'max:200'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $query = HubCommunityPost::query()
            ->where('channel', HubCommunityPost::CHANNEL_PRODUCT_UPDATE)
            ->with(['user:id,first_name,last_name,display_name'])
            ->orderByDesc('created_at');

        if (! empty($validated['q'])) {
            $term = '%'.$validated['q'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('body', 'like', $term);
            });
        }

        $paginator = $query->paginate($perPage);
        $data = collect($paginator->items())->map(fn (HubCommunityPost $p) => $this->serializeAdminRow($p))->values();

        return response()->json([
            'success' => true,
            'message' => 'Product updates.',
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $post = $this->resolveProductUpdateOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Product update.',
            'data' => $this->serializeAdminDetail($post),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $post = new HubCommunityPost([
            'user_id' => $request->user()->id,
            'channel' => HubCommunityPost::CHANNEL_PRODUCT_UPDATE,
            'title' => $data['title'],
            'body' => $data['body'],
            'comments_count' => 0,
        ]);
        $post->save();
        $post->load(['user:id,first_name,last_name,display_name']);

        return response()->json([
            'success' => true,
            'message' => 'Product update published.',
            'data' => $this->serializeAdminDetail($post),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $post = $this->resolveProductUpdateOrFail($id);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string', 'max:20000'],
        ]);

        if (array_key_exists('title', $data)) {
            $post->title = $data['title'];
        }
        if (array_key_exists('body', $data)) {
            $post->body = $data['body'];
        }
        $post->save();
        $post->load(['user:id,first_name,last_name,display_name']);

        return response()->json([
            'success' => true,
            'message' => 'Product update saved.',
            'data' => $this->serializeAdminDetail($post),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $post = $this->resolveProductUpdateOrFail($id);
        $post->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product update removed.',
            'data' => null,
        ]);
    }

    private function resolveProductUpdateOrFail(int $id): HubCommunityPost
    {
        return HubCommunityPost::query()
            ->where('id', $id)
            ->where('channel', HubCommunityPost::CHANNEL_PRODUCT_UPDATE)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAdminRow(HubCommunityPost $p): array
    {
        return [
            'id' => $p->id,
            'uuid' => $p->uuid,
            'title' => $p->title,
            'excerpt' => Str::limit(strip_tags($p->body), 160),
            'comments_count' => (int) $p->comments_count,
            'author' => $this->serializeAuthor($p),
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAdminDetail(HubCommunityPost $p): array
    {
        return [
            'id' => $p->id,
            'uuid' => $p->uuid,
            'title' => $p->title,
            'body' => $p->body,
            'comments_count' => (int) $p->comments_count,
            'author' => $this->serializeAuthor($p),
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAuthor(HubCommunityPost $p): array
    {
        $user = $p->user;
        if ($user === null) {
            return ['id' => null, 'display_name' => 'Unknown'];
        }
        $name = $user->display_name;
        if ($name === null || trim((string) $name) === '') {
            $name = trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: 'Member';
        }

        return [
            'id' => $user->id,
            'display_name' => $name,
        ];
    }
}
