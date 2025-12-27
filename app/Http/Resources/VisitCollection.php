<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Visit Collection Resource
 *
 * Transforms Visit collection to API response format
 */
class VisitCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'data' => VisitResource::collection($this->collection),
            'links' => [
                'self' => $request->fullUrl(),
            ],
            'meta' => [
                'current_page' => $this->currentPage(),
                'from' => $this->firstItem(),
                'last_page' => $this->lastPage(),
                'links' => $this->linkCollection()->toArray(),
                'path' => $request->url(),
                'per_page' => $this->perPage(),
                'to' => $this->lastItem(),
                'total' => $this->total(),
            ],
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Response  $response
     * @return void
     */
    public function withResponse($request, $response)
    {
        $response->header('Content-Type', 'application/json');
    }

    /**
     * Add additional meta data to the resource response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function with($request)
    {
        return [
            'success' => true,
            'message' => 'Visits retrieved successfully.',
            'meta' => [
                'version' => '1.0',
                'timestamp' => now()->toIso8601String(),
                'count' => $this->count(),
            ],
        ];
    }
}