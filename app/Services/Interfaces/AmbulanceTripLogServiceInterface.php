<?php

namespace App\Services\Interfaces;

use App\Http\Resources\AmbulanceTripLogResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

interface AmbulanceTripLogServiceInterface
{
    public function getByTrip(int $tripId): ResourceCollection;
    public function create(array $data): AmbulanceTripLogResource;
    public function delete(int $id): bool;
}
