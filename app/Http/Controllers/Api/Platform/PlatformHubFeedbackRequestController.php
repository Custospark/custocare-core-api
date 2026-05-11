<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\HubFeedbackRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformHubFeedbackRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'   => ['nullable', 'string', Rule::in(HubFeedbackRequest::allowedStatuses())],
            'category' => ['nullable', 'string', Rule::in(HubFeedbackRequest::allowedCategories())],
            'q'        => ['nullable', 'string', 'max:200'],
        ]);

        $query = HubFeedbackRequest::query()
            ->with(['user:id,first_name,last_name,display_name'])
            ->withCount('votes')
            ->orderByDesc('created_at');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        if (! empty($validated['q'])) {
            $term = '%'.$validated['q'].'%';
            $query->where(function ($q) use ($term): void {
                $q->where('subject', 'like', $term)->orWhere('body', 'like', $term);
            });
        }

        $items = $query->limit(500)->get();

        return response()->json([
            'success' => true,
            'message' => 'Hub feedback and requests retrieved successfully.',
            'data'    => $items->map(fn (HubFeedbackRequest $r) => $this->serializeAdminRow($r))->values(),
        ]);
    }

    public function show(Request $request, HubFeedbackRequest $hubFeedbackRequest): JsonResponse
    {
        $hubFeedbackRequest->load(['user:id,first_name,last_name,display_name']);
        $hubFeedbackRequest->loadCount('votes');

        return response()->json([
            'success' => true,
            'message' => 'Feedback request retrieved successfully.',
            'data'    => $this->serializeAdminDetail($hubFeedbackRequest),
        ]);
    }

    public function update(Request $request, HubFeedbackRequest $hubFeedbackRequest): JsonResponse
    {
        $data = $request->validate([
            'status'                 => ['sometimes', 'string', Rule::in(HubFeedbackRequest::allowedStatuses())],
            'staff_reply'            => ['nullable', 'string', 'max:20000'],
            'admin_internal_notes'   => ['nullable', 'string', 'max:20000'],
            'include_in_roadmap'       => ['sometimes', 'boolean'],
        ]);

        $hubFeedbackRequest->fill($data);

        if ($hubFeedbackRequest->category === HubFeedbackRequest::CATEGORY_FEEDBACK) {
            $hubFeedbackRequest->include_in_roadmap = false;
        }

        $hubFeedbackRequest->save();

        $hubFeedbackRequest->load(['user:id,first_name,last_name,display_name']);
        $hubFeedbackRequest->loadCount('votes');

        return response()->json([
            'success' => true,
            'message' => 'Feedback request updated successfully.',
            'data'    => $this->serializeAdminDetail($hubFeedbackRequest),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAdminRow(HubFeedbackRequest $r): array
    {
        return [
            'id'                 => $r->id,
            'uuid'               => $r->uuid,
            'user_id'            => $r->user_id,
            'user_display'       => $r->user?->full_name ?? ('User #'.$r->user_id),
            'category'           => $r->category,
            'subject'            => $r->subject,
            'status'             => $r->status,
            'include_in_roadmap' => $r->include_in_roadmap,
            'votes_count'        => (int) ($r->votes_count ?? 0),
            'created_at'         => $r->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAdminDetail(HubFeedbackRequest $r): array
    {
        return [
            'id'                   => $r->id,
            'uuid'                 => $r->uuid,
            'user_id'              => $r->user_id,
            'user_display'         => $r->user?->full_name ?? ('User #'.$r->user_id),
            'category'             => $r->category,
            'subject'              => $r->subject,
            'body'                 => $r->body,
            'status'               => $r->status,
            'include_in_roadmap'   => $r->include_in_roadmap,
            'staff_reply'          => $r->staff_reply,
            'admin_internal_notes' => $r->admin_internal_notes,
            'votes_count'          => (int) ($r->votes_count ?? 0),
            'created_at'           => $r->created_at?->toIso8601String(),
            'updated_at'           => $r->updated_at?->toIso8601String(),
        ];
    }
}
