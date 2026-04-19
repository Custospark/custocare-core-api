<?php
// app/Http/Resources/AllergyCollection.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AllergyCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => AllergyResource::collection($this->collection),
            'meta' => [
                'total' => $this->collection->count(),
                'active_count' => $this->collection->filter(fn($a) => $a->is_active)->count(),
                'severe_count' => $this->collection->filter(fn($a) => $a->isSevere())->count(),
            ],
        ];
    }
}