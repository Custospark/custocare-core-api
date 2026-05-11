<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\HubSupportTicket;
use App\Models\HubSupportTicketUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformHubSupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(HubSupportTicket::allowedStatuses())],
            'category' => ['nullable', 'string', Rule::in(HubSupportTicket::allowedCategories())],
            'priority' => ['nullable', 'string', Rule::in(HubSupportTicket::allowedPriorities())],
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        $query = HubSupportTicket::query()
            ->with(['user:id,first_name,last_name,display_name'])
            ->orderByDesc('created_at');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        if (! empty($validated['priority'])) {
            $query->where('priority', $validated['priority']);
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
            'message' => 'Hub support tickets retrieved successfully.',
            'data' => $items->map(fn (HubSupportTicket $t) => $this->serializeAdminRow($t))->values(),
        ]);
    }

    public function show(Request $request, HubSupportTicket $hubSupportTicket): JsonResponse
    {
        $hubSupportTicket->load(['user:id,first_name,last_name,display_name']);
        $hubSupportTicket->load(['updates' => function ($q) {
            $q->orderBy('created_at', 'asc');
        }]);

        return response()->json([
            'success' => true,
            'message' => 'Support ticket retrieved successfully.',
            'data' => $this->serializeAdminDetail($hubSupportTicket),
        ]);
    }

    public function update(Request $request, HubSupportTicket $hubSupportTicket): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(HubSupportTicket::allowedStatuses())],
            'priority' => ['sometimes', 'string', Rule::in(HubSupportTicket::allowedPriorities())],
            'staff_reply' => ['nullable', 'string', 'max:20000'],
            'admin_internal_notes' => ['nullable', 'string', 'max:20000'],
        ]);

        $previousStatus = $hubSupportTicket->status;

        $hubSupportTicket->fill($data);
        $hubSupportTicket->save();

        if (isset($data['status']) && $data['status'] !== $previousStatus) {
            HubSupportTicketUpdate::query()->create([
                'hub_support_ticket_id' => $hubSupportTicket->id,
                'status' => $hubSupportTicket->status,
                'note' => 'Status updated by platform support.',
            ]);
        }

        $hubSupportTicket->load(['user:id,first_name,last_name,display_name']);
        $hubSupportTicket->load(['updates' => function ($q) {
            $q->orderBy('created_at', 'asc');
        }]);

        return response()->json([
            'success' => true,
            'message' => 'Support ticket updated successfully.',
            'data' => $this->serializeAdminDetail($hubSupportTicket),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAdminRow(HubSupportTicket $t): array
    {
        return [
            'id' => $t->id,
            'uuid' => $t->uuid,
            'user_id' => $t->user_id,
            'user_display' => $t->user?->full_name ?? ('User #'.$t->user_id),
            'category' => $t->category,
            'priority' => $t->priority,
            'subject' => $t->subject,
            'status' => $t->status,
            'created_at' => $t->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAdminDetail(HubSupportTicket $t): array
    {
        return [
            'id' => $t->id,
            'uuid' => $t->uuid,
            'user_id' => $t->user_id,
            'user_display' => $t->user?->full_name ?? ('User #'.$t->user_id),
            'category' => $t->category,
            'priority' => $t->priority,
            'subject' => $t->subject,
            'body' => $t->body,
            'status' => $t->status,
            'staff_reply' => $t->staff_reply,
            'admin_internal_notes' => $t->admin_internal_notes,
            'created_at' => $t->created_at?->toIso8601String(),
            'updated_at' => $t->updated_at?->toIso8601String(),
            'timeline' => $t->updates?->map(function (HubSupportTicketUpdate $u): array {
                return [
                    'uuid' => $u->uuid,
                    'status' => $u->status,
                    'note' => $u->note,
                    'created_at' => $u->created_at?->toIso8601String(),
                ];
            })->values(),
        ];
    }
}
