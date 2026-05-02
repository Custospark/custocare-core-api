<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class VitalCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => VitalResource::collection($this->collection),
            'meta' => [
                'total_count' => $this->collection->count(),
                'abnormal_count' => $this->collection->filter(fn($v) => !empty($v->flag_status) && isset($v->flag_status['warning']))->count(),
                'critical_count' => $this->collection->filter(fn($v) => !empty($v->clinical_alert))->count(),
                'fever_count' => $this->collection->filter(fn($v) => $v->hasFever())->count(),
                'hypertensive_count' => $this->collection->filter(fn($v) => $v->isHypertensive())->count(),
                'hypoxic_count' => $this->collection->filter(fn($v) => $v->isHypoxic())->count(),
                'average_temperature' => $this->collection->avg('temperature'),
                'average_heart_rate' => $this->collection->avg('heart_rate'),
                'average_oxygen_saturation' => $this->collection->avg('oxygen_saturation'),
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