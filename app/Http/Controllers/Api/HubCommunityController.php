<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HubCommunityComment;
use App\Models\HubCommunityPost;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HubCommunityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['nullable', 'string', Rule::in(HubCommunityPost::allowedChannels())],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);
        $query = HubCommunityPost::query()
            ->with(['user:id,first_name,last_name,display_name'])
            ->orderByDesc('created_at');

        if (! empty($validated['channel'])) {
            $query->where('channel', $validated['channel']);
        }

        $paginator = $query->paginate($perPage);

        $data = collect($paginator->items())->map(fn (HubCommunityPost $p) => $this->serializePostSummary($p))->values();

        return response()->json([
            'success' => true,
            'message' => 'Community posts.',
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(string $uuid): JsonResponse
    {
        $post = HubCommunityPost::query()
            ->where('uuid', $uuid)
            ->with([
                'user:id,first_name,last_name,display_name',
                'comments.user:id,first_name,last_name,display_name',
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Post detail.',
            'data' => [
                'post' => $this->serializePostDetail($post),
                'comments' => $post->comments->map(fn (HubCommunityComment $c) => $this->serializeComment($c))->values(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'string', Rule::in(HubCommunityPost::allowedUserComposeChannels())],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $post = new HubCommunityPost([
            'user_id' => $request->user()->id,
            'channel' => $data['channel'],
            'title' => $data['title'],
            'body' => $data['body'],
            'comments_count' => 0,
        ]);
        $post->save();
        $post->load(['user:id,first_name,last_name,display_name']);

        return response()->json([
            'success' => true,
            'message' => 'Your post is live in the community.',
            'data' => $this->serializePostDetail($post),
        ], 201);
    }

    public function storeComment(Request $request, string $uuid): JsonResponse
    {
        $post = HubCommunityPost::query()->where('uuid', $uuid)->firstOrFail();

        if ($post->channel === HubCommunityPost::CHANNEL_PRODUCT_UPDATE) {
            return response()->json([
                'success' => false,
                'message' => 'Product updates are read-only in the hub.',
                'data' => null,
            ], 403);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:8000'],
        ]);

        $comment = DB::transaction(function () use ($post, $request, $data): HubCommunityComment {
            $row = HubCommunityComment::create([
                'hub_community_post_id' => $post->id,
                'user_id' => $request->user()->id,
                'body' => $data['body'],
            ]);
            $post->increment('comments_count');

            return $row->load(['user:id,first_name,last_name,display_name']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Comment added.',
            'data' => $this->serializeComment($comment),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAuthor(?User $user): array
    {
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

    /**
     * @return array<string, mixed>
     */
    private function serializePostSummary(HubCommunityPost $p): array
    {
        return [
            'uuid' => $p->uuid,
            'channel' => $p->channel,
            'title' => $p->title,
            'excerpt' => Str::limit(strip_tags($p->body), 220),
            'comments_count' => (int) $p->comments_count,
            'author' => $this->serializeAuthor($p->user),
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePostDetail(HubCommunityPost $p): array
    {
        return [
            'uuid' => $p->uuid,
            'channel' => $p->channel,
            'title' => $p->title,
            'body' => $p->body,
            'comments_count' => (int) $p->comments_count,
            'author' => $this->serializeAuthor($p->user),
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeComment(HubCommunityComment $c): array
    {
        return [
            'uuid' => $c->uuid,
            'body' => $c->body,
            'author' => $this->serializeAuthor($c->user),
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
