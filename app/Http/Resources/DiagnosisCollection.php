<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class DiagnosisCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => DiagnosisResource::collection($this->collection),
            'meta' => [
                'total_count' => $this->collection->count(),
                'primary_count' => $this->collection->filter(fn($d) => $d->isPrimary())->count(),
                'secondary_count' => $this->collection->filter(fn($d) => $d->isSecondary())->count(),
                'active_count' => $this->collection->filter(fn($d) => $d->isActive())->count(),
                'resolved_count' => $this->collection->filter(fn($d) => $d->isResolved())->count(),
                'verified_count' => $this->collection->filter(fn($d) => $d->isVerified())->count(),
                'disputed_count' => $this->collection->filter(fn($d) => $d->isDisputed())->count(),
                'confirmed_count' => $this->collection->filter(fn($d) => $d->isConfirmed())->count(),
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