<?php

namespace App\Services\VisitActor;

use App\Models\VisitActor;
use App\Services\Contracts\VisitActorServiceInterface;
use App\Repositories\Contracts\VisitActorRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VisitActorService implements VisitActorServiceInterface
{
    /**
     * @var VisitActorRepositoryInterface
     */
    protected $visitActorRepository;

    /**
     * VisitActorService constructor.
     *
     * @param VisitActorRepositoryInterface $visitActorRepository
     */
    public function __construct(VisitActorRepositoryInterface $visitActorRepository)
    {
        $this->visitActorRepository = $visitActorRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllVisitActors(array $filters = []): LengthAwarePaginator
    {
        try {
            // Apply business logic filters
            $perPage = $filters['per_page'] ?? 15;
            
            // Filter by date range if provided
            if (isset($filters['date_from']) || isset($filters['date_to'])) {
                $query = VisitActor::query();
                
                if (isset($filters['date_from'])) {
                    $query->whereDate('participation_started_at', '>=', $filters['date_from']);
                }
                
                if (isset($filters['date_to'])) {
                    $query->whereDate('participation_started_at', '<=', $filters['date_to']);
                }
                
                if (isset($filters['facility_id'])) {
                    $query->where('facility_id', $filters['facility_id']);
                }
                
                if (isset($filters['participation_type'])) {
                    $query->where('participation_type', $filters['participation_type']);
                }
                
                return $query->with(['visit', 'staff', 'facility'])
                    ->orderBy('participation_started_at', 'desc')
                    ->paginate($perPage);
            }
            
            return $this->visitActorRepository->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get all visit actors', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return new LengthAwarePaginator([], 0, $filters['per_page'] ?? 15);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVisitActorById(int $id): ?VisitActor
    {
        try {
            $visitActor = $this->visitActorRepository->find($id);
            
            if (!$visitActor) {
                Log::warning('Visit actor not found', ['id' => $id]);
                return null;
            }
            
            return $visitActor;
        } catch (\Exception $e) {
            Log::error('Failed to get visit actor by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createVisitActor(array $data): array
    {
        try {
            // Validate business rules before creation
            $validation = $this->validateParticipationData($data);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors'],
                    'data' => null
                ];
            }
            
            // Check for duplicate participation
            if ($this->visitActorRepository->isDuplicateParticipation(
                $data['visit_id'],
                $data['staff_id'],
                $data['participation_type'],
                $data['participation_started_at']
            )) {
                return [
                    'success' => false,
                    'message' => 'Duplicate participation detected for this staff member, visit, and start time.',
                    'errors' => ['participation' => 'Duplicate participation record'],
                    'data' => null
                ];
            }
            
            // Calculate time involvement if ended time is provided
            if (isset($data['participation_ended_at']) && $data['participation_started_at']) {
                $startedAt = new \DateTime($data['participation_started_at']);
                $endedAt = new \DateTime($data['participation_ended_at']);
                $data['time_involvement_minutes'] = $endedAt->diff($startedAt)->i + ($endedAt->diff($startedAt)->h * 60);
            }
            
            // Set default billing flags based on participation type
            if (!isset($data['is_billable_provider'])) {
                $data['is_billable_provider'] = in_array($data['participation_type'], [
                    'primary_provider',
                    'consulting_provider',
                    'supervising_provider',
                    'anesthesiologist'
                ]);
            }
            
            DB::beginTransaction();
            
            $visitActor = $this->visitActorRepository->create($data);
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Visit actor created successfully',
                'data' => $visitActor,
                'metadata' => [
                    'is_billable' => $visitActor->isBillable(),
                    'is_active' => $visitActor->isActive()
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create visit actor', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create visit actor. Please try again.',
                'errors' => ['server' => 'Internal server error'],
                'data' => null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateVisitActor(int $id, array $data): array
    {
        try {
            // Check if visit actor exists
            $visitActor = $this->getVisitActorById($id);
            if (!$visitActor) {
                return [
                    'success' => false,
                    'message' => 'Visit actor not found',
                    'errors' => ['id' => 'Visit actor not found with ID: ' . $id],
                    'data' => null
                ];
            }
            
            // Validate business rules for update
            $validation = $this->validateParticipationData($data, $id);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors'],
                    'data' => null
                ];
            }
            
            DB::beginTransaction();
            
            $updatedVisitActor = $this->visitActorRepository->update($id, $data);
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Visit actor updated successfully',
                'data' => $updatedVisitActor,
                'metadata' => [
                    'is_billable' => $updatedVisitActor->isBillable(),
                    'is_active' => $updatedVisitActor->isActive()
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update visit actor', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update visit actor. Please try again.',
                'errors' => ['server' => 'Internal server error'],
                'data' => null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteVisitActor(int $id): array
    {
        try {
            // Check if visit actor exists
            $visitActor = $this->getVisitActorById($id);
            if (!$visitActor) {
                return [
                    'success' => false,
                    'message' => 'Visit actor not found',
                    'errors' => ['id' => 'Visit actor not found with ID: ' . $id],
                    'data' => null
                ];
            }
            
            // Prevent deletion of billable records that are already billed
            if ($visitActor->is_billable_provider && $visitActor->provider_charge_amount > 0) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete billable visit actor with charge amount.',
                    'errors' => ['billing' => 'Billable records cannot be deleted'],
                    'data' => null
                ];
            }
            
            $deleted = $this->visitActorRepository->delete($id);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete visit actor',
                    'errors' => ['delete' => 'Failed to delete record'],
                    'data' => null
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Visit actor deleted successfully',
                'data' => null,
                'metadata' => ['deleted_id' => $id]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete visit actor', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to delete visit actor. Please try again.',
                'errors' => ['server' => 'Internal server error'],
                'data' => null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function endParticipation(int $id, array $data): array
    {
        try {
            // Check if visit actor exists
            $visitActor = $this->getVisitActorById($id);
            if (!$visitActor) {
                return [
                    'success' => false,
                    'message' => 'Visit actor not found',
                    'errors' => ['id' => 'Visit actor not found with ID: ' . $id],
                    'data' => null
                ];
            }
            
            // Check if participation is already ended
            if ($visitActor->participation_ended_at) {
                return [
                    'success' => false,
                    'message' => 'Participation already ended',
                    'errors' => ['participation' => 'Participation already ended at: ' . $visitActor->participation_ended_at],
                    'data' => null
                ];
            }
            
            // Validate end time is after start time
            $endedAt = $data['participation_ended_at'] ?? now();
            if ($endedAt < $visitActor->participation_started_at) {
                return [
                    'success' => false,
                    'message' => 'End time must be after start time',
                    'errors' => ['participation_ended_at' => 'End time must be after start time'],
                    'data' => null
                ];
            }
            
            DB::beginTransaction();
            
            $endedVisitActor = $this->visitActorRepository->endParticipation($id, $data);
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Participation ended successfully',
                'data' => $endedVisitActor,
                'metadata' => [
                    'duration_minutes' => $endedVisitActor->time_involvement_minutes,
                    'is_billable' => $endedVisitActor->isBillable()
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to end participation', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to end participation. Please try again.',
                'errors' => ['server' => 'Internal server error'],
                'data' => null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVisitActorsByVisit(int $visitId): Collection
    {
        try {
            return $this->visitActorRepository->findByVisit($visitId);
        } catch (\Exception $e) {
            Log::error('Failed to get visit actors by visit', [
                'visit_id' => $visitId,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveStaffParticipations(int $staffId): Collection
    {
        try {
            return $this->visitActorRepository->getActiveParticipations($staffId);
        } catch (\Exception $e) {
            Log::error('Failed to get active staff participations', [
                'staff_id' => $staffId,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateParticipationData(array $data, ?int $id = null): array
    {
        $rules = [
            'facility_id' => 'required|integer|exists:facilities,id',
            'visit_id' => 'required|integer|exists:visits,id',
            'staff_id' => 'required|integer|exists:staff,id',
            'role_at_time' => 'required|string|max:100',
            'credential_snapshot_id' => 'nullable|integer|exists:staff_credentials,id',
            'participation_type' => 'required|in:' . implode(',', array_values(VisitActor::PARTICIPATION_TYPES)),
            'participation_started_at' => 'required|date',
            'participation_ended_at' => 'nullable|date|after:participation_started_at',
            'time_involvement_minutes' => 'nullable|integer|min:0|max:1440',
            'department_id_at_time' => 'nullable|integer',
            'services_performed' => 'nullable|array',
            'services_performed.*' => 'string|max:20',
            'procedures_assisted' => 'nullable|array',
            'procedures_assisted.*' => 'string|max:100',
            'is_billable_provider' => 'boolean',
            'provider_charge_amount' => 'nullable|numeric|min:0|max:9999999.99',
            'is_teaching_case' => 'boolean',
            'supervising_staff_id' => 'nullable|integer|exists:staff,id',
            'metadata' => 'nullable|array',
        ];
        
        // For updates, make some fields optional
        if ($id) {
            $rules = array_map(function ($rule) {
                return str_replace('required', 'sometimes', $rule);
            }, $rules);
        }
        
        $validator = Validator::make($data, $rules);
        
        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->toArray()
            ];
        }
        
        return ['valid' => true, 'errors' => []];
    }
}