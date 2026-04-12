<?php

namespace App\Services\FacilitySpaceService;

use App\Models\FacilitySpace;
use Illuminate\Support\Facades\Log;

class FacilitySpaceService
{
    public function listSpaces(int $facilityId, bool $activeOnly = false)
    {
        $q = FacilitySpace::query()->where('facility_id', $facilityId);

        if ($activeOnly) {
            $q->active();
        }

        return $q->orderBy('type')
            ->orderBy('building')
            ->orderBy('floor')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a new space or return existing one if it already exists
     * 
     * @param array $data
     * @return FacilitySpace
     */
    public function createSpace(array $data): FacilitySpace
    {
        // Check if a space with the same name already exists in this facility
        $existingSpace = FacilitySpace::where('facility_id', $data['facility_id'])
            ->where('name', $data['name'])
            ->first();

        if ($existingSpace) {
            Log::info('Space already exists, returning existing record', [
                'facility_id' => $data['facility_id'],
                'name' => $data['name'],
                'space_id' => $existingSpace->id
            ]);
            
            return $existingSpace;
        }

        // Check for duplicate based on unique constraint (safety check)
        try {
            $space = FacilitySpace::create($data);
            
            Log::info('New space created', [
                'facility_id' => $data['facility_id'],
                'name' => $data['name'],
                'space_id' => $space->id
            ]);
            
            return $space;
        } catch (\Illuminate\Database\QueryException $e) {
            // If it's a duplicate entry error (unique constraint violation)
            if ($e->errorInfo[1] == 1062) { // MySQL duplicate entry error code
                Log::warning('Duplicate space detected during creation, fetching existing', [
                    'facility_id' => $data['facility_id'],
                    'name' => $data['name'],
                    'error' => $e->getMessage()
                ]);
                
                $existingSpace = FacilitySpace::where('facility_id', $data['facility_id'])
                    ->where('name', $data['name'])
                    ->first();
                    
                if ($existingSpace) {
                    return $existingSpace;
                }
            }
            
            throw $e;
        }
    }

    /**
     * Find or create a space
     * 
     * @param array $data
     * @return FacilitySpace
     */
    public function findOrCreateSpace(array $data): FacilitySpace
    {
        return $this->createSpace($data);
    }

    /**
     * Get or create space by name and facility
     * 
     * @param int $facilityId
     * @param string $name
     * @param array $additionalData
     * @return FacilitySpace
     */
    public function getOrCreateSpace(int $facilityId, string $name, array $additionalData = []): FacilitySpace
    {
        $space = FacilitySpace::where('facility_id', $facilityId)
            ->where('name', $name)
            ->first();

        if ($space) {
            return $space;
        }

        $data = array_merge([
            'facility_id' => $facilityId,
            'name' => $name,
            'type' => $additionalData['type'] ?? 'consultation',
            'floor' => $additionalData['floor'] ?? null,
            'building' => $additionalData['building'] ?? null,
            'is_active' => $additionalData['is_active'] ?? true,
        ], $additionalData);

        return FacilitySpace::create($data);
    }

    public function updateSpace(FacilitySpace $space, array $data): FacilitySpace
    {
        // Check if updating to a name that already exists (excluding current space)
        if (isset($data['name']) && $data['name'] !== $space->name) {
            $existingSpace = FacilitySpace::where('facility_id', $space->facility_id)
                ->where('name', $data['name'])
                ->where('id', '!=', $space->id)
                ->first();

            if ($existingSpace) {
                Log::warning('Cannot update space: name already exists', [
                    'facility_id' => $space->facility_id,
                    'current_name' => $space->name,
                    'requested_name' => $data['name'],
                    'existing_space_id' => $existingSpace->id
                ]);
                
                throw new \Exception('A space with this name already exists in the facility.');
            }
        }

        $space->update($data);
        
        Log::info('Space updated', [
            'facility_id' => $space->facility_id,
            'name' => $space->name,
            'space_id' => $space->id
        ]);
        
        return $space->refresh();
    }

    public function deleteSpace(FacilitySpace $space): void
    {
        Log::info('Space deleted', [
            'facility_id' => $space->facility_id,
            'name' => $space->name,
            'space_id' => $space->id
        ]);
        
        $space->delete();
    }

    /**
     * Check if a space exists in the facility
     * 
     * @param int $facilityId
     * @param string $name
     * @return bool
     */
    public function spaceExists(int $facilityId, string $name): bool
    {
        return FacilitySpace::where('facility_id', $facilityId)
            ->where('name', $name)
            ->exists();
    }

    /**
     * Find space by name and facility
     * 
     * @param int $facilityId
     * @param string $name
     * @return FacilitySpace|null
     */
    public function findSpaceByName(int $facilityId, string $name): ?FacilitySpace
    {
        return FacilitySpace::where('facility_id', $facilityId)
            ->where('name', $name)
            ->first();
    }
}