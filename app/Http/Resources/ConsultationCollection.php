<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ConsultationCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => ConsultationResource::collection($this->collection),
            'meta' => [
                'total_count' => $this->collection->count(),
                'pending_count' => $this->collection->filter(fn($c) => $c->isPending())->count(),
                'accepted_count' => $this->collection->filter(fn($c) => $c->isAccepted())->count(),
                'completed_count' => $this->collection->filter(fn($c) => $c->isCompleted())->count(),
                'declined_count' => $this->collection->filter(fn($c) => $c->isDeclined())->count(),
                'cancelled_count' => $this->collection->filter(fn($c) => $c->isCancelled())->count(),
                'urgent_count' => $this->collection->filter(fn($c) => $c->isUrgent())->count(),
                'overdue_count' => $this->collection->filter(fn($c) => $c->isOverdue())->count(),
                'requires_followup_count' => $this->collection->filter(fn($c) => $c->requiresFollowup())->count(),
            ],
        ];
    }

    /**
     * Customize the pagination information for the resource.
     *
     * @param \Illuminate\Http\Request $request
     * @param array $paginated
     * @param array $default
     * @return array
     */
    public function paginationInformation($request, $paginated, $default)
    {
        return [
            'current_page' => $default['meta']['current_page'],
            'last_page' => $default['meta']['last_page'],
            'per_page' => $default['meta']['per_page'],
            'total' => $default['meta']['total'],
            'from' => $default['meta']['from'] ?? null,
            'to' => $default['meta']['to'] ?? null,
        ];
    }
}