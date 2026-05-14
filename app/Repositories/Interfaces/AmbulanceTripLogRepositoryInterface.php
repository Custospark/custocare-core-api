<?php

namespace App\Repositories\Interfaces;

use App\Models\AmbulanceTripLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface AmbulanceTripLogRepositoryInterface
{
    public function getByTrip(int $tripId): Collection;
    public function create(array $data): AmbulanceTripLog;
    public function delete(int $id): bool;
}
