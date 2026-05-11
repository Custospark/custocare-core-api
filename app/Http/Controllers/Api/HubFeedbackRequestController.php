<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HubFeedbackRequest;
use App\Models\HubFeedbackVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HubFeedbackRequestController extends Controller
{
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        $items = HubFeedbackRequest::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Your feedback and requests.',
            'data'    => $items->map(fn (HubFeedbackRequest $r) => $this->serializeMine($r))->values(),
        ]);
    }

    public function roadmap(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $items = HubFeedbackRequest::query()
            ->where('category', HubFeedbackRequest::CATEGORY_FEATURE_REQUEST)
            ->where('include_in_roadmap', true)
            ->whereIn('status', [
                HubFeedbackRequest::STATUS_SUBMITTED,
                HubFeedbackRequest::STATUS_ACKNOWLEDGED,
                HubFeedbackRequest::STATUS_IN_PROGRESS,
            ])
            ->withCount('votes')
            ->orderByDesc('votes_count')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $votedIds = HubFeedbackVote::query()
            ->where('user_id', $userId)
            ->whereIn('hub_feedback_request_id', $items->pluck('id'))
            ->pluck('hub_feedback_request_id')
            ->all();

        $votedSet = array_fill_keys($votedIds, true);

        return response()->json([
            'success' => true,
            'message' => 'Feature ideas open for community votes.',
            'data'    => $items->map(function (HubFeedbackRequest $r) use ($votedSet) {
                return [
                    'uuid'         => $r->uuid,
                    'subject'      => $r->subject,
                    'excerpt'      => Str::limit((string) $r->body, 220),
                    'votes_count'  => (int) $r->votes_count,
                    'voted_by_you' => isset($votedSet[$r->id]),
                    'created_at'   => $r->created_at?->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category'              => ['required', 'string', Rule::in(HubFeedbackRequest::allowedCategories())],
            'subject'               => ['required', 'string', 'max:200'],
            'body'                  => ['required', 'string', 'max:20000'],
            'include_in_roadmap'    => ['nullable', 'boolean'],
        ]);

        $include = (bool) ($data['include_in_roadmap'] ?? false);
        if ($data['category'] === HubFeedbackRequest::CATEGORY_FEEDBACK) {
            $include = false;
        }

        $row = new HubFeedbackRequest([
            'user_id'              => $request->user()->id,
            'category'             => $data['category'],
            'subject'              => $data['subject'],
            'body'                 => $data['body'],
            'status'               => HubFeedbackRequest::STATUS_SUBMITTED,
            'include_in_roadmap'   => $include,
        ]);
        $row->save();

        return response()->json([
            'success' => true,
            'message' => 'Thank you — we received your submission.',
            'data'    => $this->serializeMine($row->fresh()),
        ], 201);
    }

    public function vote(Request $request, string $uuid): JsonResponse
    {
        $row = HubFeedbackRequest::query()->where('uuid', $uuid)->firstOrFail();

        if (! $row->isOpenForRoadmapVoting()) {
            return response()->json([
                'success' => false,
                'message' => 'This item is not open for voting.',
                'data'    => null,
            ], 422);
        }

        if ($row->user_id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot vote on your own submission.',
                'data'    => null,
            ], 422);
        }

        $vote = HubFeedbackVote::query()->firstOrCreate(
            [
                'hub_feedback_request_id' => $row->id,
                'user_id'                   => $request->user()->id,
            ],
        );

        $row->loadCount('votes');

        return response()->json([
            'success' => true,
            'message' => $vote->wasRecentlyCreated ? 'Vote recorded.' : 'You already voted for this idea.',
            'data'    => [
                'uuid'        => $row->uuid,
                'votes_count' => (int) $row->votes_count,
                'voted_by_you'=> true,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMine(HubFeedbackRequest $r): array
    {
        return [
            'id'               => $r->id,
            'uuid'             => $r->uuid,
            'category'         => $r->category,
            'subject'          => $r->subject,
            'body'             => $r->body,
            'status'           => $r->status,
            'include_in_roadmap' => $r->include_in_roadmap,
            'staff_reply'      => $r->staff_reply,
            'created_at'       => $r->created_at?->toIso8601String(),
            'updated_at'       => $r->updated_at?->toIso8601String(),
        ];
    }
}
