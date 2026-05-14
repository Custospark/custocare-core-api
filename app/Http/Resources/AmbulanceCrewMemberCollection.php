<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class AmbulanceCrewMemberCollection extends ResourceCollection
{
    public $collects = AmbulanceCrewMemberResource::class;

    public function toArray($request)
    {
        return [
            'data' => $this->collection,
            'meta' => ['count' => $this->count()],
        ];
    }
}
