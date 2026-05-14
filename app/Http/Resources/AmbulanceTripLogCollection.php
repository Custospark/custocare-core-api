<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class AmbulanceTripLogCollection extends ResourceCollection
{
    public $collects = AmbulanceTripLogResource::class;

    public function toArray($request)
    {
        return [
            'data' => $this->collection,
            'meta' => ['count' => $this->count()],
        ];
    }
}
