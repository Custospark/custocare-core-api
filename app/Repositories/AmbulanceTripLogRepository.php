<?php

namespace App\Repositories;

use App\Models\AmbulanceTripLog;
use App\Repositories\Interfaces\AmbulanceTripLogRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class AmbulanceTripLogRepository implements AmbulanceTripLogRepositoryInterface
{
    public function getByTrip(int $tripId): Collection
    {
        return AmbulanceTripLog::with(['recordedBy'])
            ->where('trip_id', $tripId)
            ->orderBy('recorded_at')
            ->get();
    }

    public function create(array $data): AmbulanceTripLog
    {
        return AmbulanceTripLog::create($data);
    }

    public function delete(int $id): bool
    {
        return AmbulanceTripLog::findOrFail($id)->delete();
    }
}
