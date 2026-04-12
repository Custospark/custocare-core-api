<?php

namespace App\Services\Ward;

use App\Models\Ward;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WardService
{
    public function list(int $facilityId, array $filters = [])
    {
        $q = Ward::query()->where('facility_id', $facilityId);

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        if (!empty($filters['ward_type'])) {
            $q->where('ward_type', $filters['ward_type']);
        }

        if (!empty($filters['search'])) {
            $term = trim((string)$filters['search']);
            $q->where(function ($sub) use ($term) {
                $sub->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('building', 'like', "%{$term}%")
                    ->orWhere('floor', 'like', "%{$term}%");
            });
        }

        return $q->orderBy('name')->get();
    }

    /**
     * Generate a unique ward code
     * Format: WD-XXXX where XXXX is a random 4-digit number
     * Example: WD-1234, WD-5678, WD-9012
     */
    private function generateUniqueWardCode(int $facilityId): string
    {
        $maxAttempts = 10;
        $attempt = 0;
        
        do {
            // Generate random number between 1 and 9999
            $randomNum = random_int(1, 9999);
            
            // Pad with leading zeros to 4 digits
            $paddedNum = str_pad($randomNum, 4, '0', STR_PAD_LEFT);
            
            // Create code in format: WD-XXXX
            $code = "WD-{$paddedNum}";
            
            // Check if this code already exists for this facility
            $exists = Ward::where('facility_id', $facilityId)
                ->where('code', $code)
                ->exists();
            
            $attempt++;
            
            if ($exists) {
                Log::info('Ward code collision detected, retrying', [
                    'facility_id' => $facilityId,
                    'attempted_code' => $code,
                    'attempt' => $attempt
                ]);
            }
            
        } while ($exists && $attempt < $maxAttempts);
        
        if ($exists) {
            Log::error('Failed to generate unique ward code after max attempts', [
                'facility_id' => $facilityId,
                'max_attempts' => $maxAttempts
            ]);
            throw new \Exception('Unable to generate a unique ward code. Please try again.');
        }
        
        return $code;
    }

    /**
     * Check if a ward code already exists in the facility
     */
    private function isWardCodeUnique(int $facilityId, ?string $code): bool
    {
        if (empty($code)) {
            return false;
        }
        
        return !Ward::where('facility_id', $facilityId)
            ->where('code', $code)
            ->exists();
    }

    public function create(array $data, int $userId): Ward
    {
        DB::beginTransaction();
        
        try {
            $data['created_by_user_id'] = $userId;
            $data['updated_by_user_id'] = $userId;

            // defaults (if not provided)
            $data['status'] = $data['status'] ?? 'active';
            $data['sex_restriction'] = $data['sex_restriction'] ?? 'mixed';
            $data['age_group'] = $data['age_group'] ?? 'all';
            $data['ward_type'] = $data['ward_type'] ?? 'general';

            // Handle ward code: generate new one if provided code exists
            $originalCode = $data['code'] ?? null;
            
            if (!empty($originalCode)) {
                // Check if the provided code already exists
                if (!$this->isWardCodeUnique($data['facility_id'], $originalCode)) {
                    Log::info('Ward code already exists, generating new code', [
                        'facility_id' => $data['facility_id'],
                        'original_code' => $originalCode
                    ]);
                    
                    // Generate a new unique code
                    $data['code'] = $this->generateUniqueWardCode($data['facility_id']);
                }
            } else {
                // No code provided, generate one
                $data['code'] = $this->generateUniqueWardCode($data['facility_id']);
            }

            $ward = Ward::create($data);
            
            DB::commit();
            
            Log::info('Ward created successfully', [
                'facility_id' => $ward->facility_id,
                'ward_id' => $ward->id,
                'ward_code' => $ward->code,
                'ward_name' => $ward->name,
                'original_code_requested' => $originalCode
            ]);
            
            return $ward;
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create ward', [
                'facility_id' => $data['facility_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    public function update(Ward $ward, array $data, int $userId): Ward
    {
        DB::beginTransaction();
        
        try {
            $data['updated_by_user_id'] = $userId;

            // If code is being updated, check for uniqueness
            if (isset($data['code']) && $data['code'] !== $ward->code) {
                $originalCode = $data['code'];
                
                if (!$this->isWardCodeUnique($ward->facility_id, $originalCode)) {
                    Log::warning('Cannot update ward: code already exists', [
                        'facility_id' => $ward->facility_id,
                        'ward_id' => $ward->id,
                        'current_code' => $ward->code,
                        'requested_code' => $originalCode
                    ]);
                    
                    throw new \Exception('A ward with this code already exists in the facility.');
                }
            }

            $ward->update($data);
            
            DB::commit();
            
            Log::info('Ward updated successfully', [
                'facility_id' => $ward->facility_id,
                'ward_id' => $ward->id,
                'ward_code' => $ward->code,
                'ward_name' => $ward->name
            ]);
            
            return $ward->refresh();
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update ward', [
                'facility_id' => $ward->facility_id,
                'ward_id' => $ward->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    public function delete(Ward $ward): void
    {
        DB::beginTransaction();
        
        try {
            Log::info('Deleting ward', [
                'facility_id' => $ward->facility_id,
                'ward_id' => $ward->id,
                'ward_code' => $ward->code,
                'ward_name' => $ward->name
            ]);
            
            $ward->delete();
            
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to delete ward', [
                'facility_id' => $ward->facility_id,
                'ward_id' => $ward->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    public function ensureFacilityScope(Ward $ward, int $facilityId): void
    {
        if ((int)$ward->facility_id !== (int)$facilityId) {
            throw new \Exception('Facility scope mismatch.');
        }
    }
    
    /**
     * Find or create a ward by name
     * If ward exists with same name, return it; otherwise create new
     */
    public function findOrCreateByName(int $facilityId, string $name, array $additionalData = [], int $userId): Ward
    {
        $ward = Ward::where('facility_id', $facilityId)
            ->where('name', $name)
            ->first();
            
        if ($ward) {
            Log::info('Found existing ward by name', [
                'facility_id' => $facilityId,
                'name' => $name,
                'ward_id' => $ward->id
            ]);
            return $ward;
        }
        
        $data = array_merge([
            'facility_id' => $facilityId,
            'name' => $name,
            'code' => $additionalData['code'] ?? $this->generateUniqueWardCode($facilityId),
            'ward_type' => $additionalData['ward_type'] ?? 'general',
            'status' => $additionalData['status'] ?? 'active',
            'sex_restriction' => $additionalData['sex_restriction'] ?? 'mixed',
            'age_group' => $additionalData['age_group'] ?? 'all',
        ], $additionalData);
        
        return $this->create($data, $userId);
    }
}