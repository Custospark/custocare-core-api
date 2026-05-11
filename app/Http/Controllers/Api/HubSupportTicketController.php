<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HubSupportTicket;
use App\Models\HubSupportTicketUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HubSupportTicketController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string', Rule::in(HubSupportTicket::allowedCategories())],
            'priority' => ['nullable', 'string', Rule::in(HubSupportTicket::allowedPriorities())],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $ticket = new HubSupportTicket([
            'user_id' => $request->user()->id,
            'category' => $data['category'],
            'priority' => $data['priority'] ?? HubSupportTicket::PRIORITY_MEDIUM,
            'subject' => $data['subject'],
            'body' => $data['body'],
            'status' => HubSupportTicket::STATUS_SUBMITTED,
            'staff_reply' => null,
        ]);

        $ticket->save();

        HubSupportTicketUpdate::query()->create([
            'hub_support_ticket_id' => $ticket->id,
            'status' => HubSupportTicket::STATUS_SUBMITTED,
            'note' => 'Ticket submitted.',
        ]);

        $ticket->load(['updates' => function ($q) {
            $q->orderBy('created_at', 'asc');
        }]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket submitted successfully.',
            'data' => $this->serializeMine($ticket),
        ], 201);
    }

    public function show(Request $request, string $ref): JsonResponse
    {
        // Security: a user can only read their own ticket.
        $ticket = HubSupportTicket::query()
            ->where('uuid', $ref)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $ticket->load(['updates' => function ($q) {
            $q->orderBy('created_at', 'asc');
        }]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket retrieved successfully.',
            'data' => $this->serializeMine($ticket),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMine(HubSupportTicket $t): array
    {
        return [
            'uuid' => $t->uuid,
            'category' => $t->category,
            'priority' => $t->priority,
            'subject' => $t->subject,
            'body' => $t->body,
            'status' => $t->status,
            'staff_reply' => $t->staff_reply,
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

