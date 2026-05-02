<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ClinicalNoteCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => ClinicalNoteResource::collection($this->collection),
            'meta' => [
                'total_count' => $this->collection->count(),
                'draft_count' => $this->collection->filter(fn($note) => $note->isDraft())->count(),
                'final_count' => $this->collection->filter(fn($note) => $note->isFinal())->count(),
                'amended_count' => $this->collection->filter(fn($note) => $note->isAmended())->count(),
                'cancelled_count' => $this->collection->filter(fn($note) => $note->isCancelled())->count(),
                'amendment_count' => $this->collection->filter(fn($note) => $note->isAmendment())->count(),
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