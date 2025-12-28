<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ClinicalDocumentCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => $this->collection,
            'meta' => [
                'total' => $this->total(),
                'count' => $this->count(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'total_pages' => $this->lastPage(),
                'links' => [
                    'next' => $this->nextPageUrl(),
                    'prev' => $this->previousPageUrl(),
                ],
            ],
            'message' => 'Clinical documents retrieved successfully',
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
            'links' => $default['links'],
            'meta' => $default['meta'],
        ];
    }
}