<?php

namespace App\Repositories\VisitActor;

use App\Models\VisitActor;
use App\Repositories\Contracts\VisitActorRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VisitActorRepository implements VisitActorRepositoryInterface
{
    /**
     * @var VisitActor
     */
    protected $model;

    /**
     * VisitActorRepository constructor.
     *
     * @param VisitActor $model
     */
    public function __construct(VisitActor $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id): ?VisitActor
    {
        try {
            return $this->model->with(['visit', 'staff', 'supervisingStaff', 'credentialSnapshot'])->find($id);
        } catch (\Exception $e) {
            Log::error('Failed to find visit actor', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        try {
            return $this->model
                ->with(['visit', 'staff'])
                ->orderBy('participation_started_at', 'desc')
                ->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to paginate visit actors', [
                'error' => $e->getMessage()
            ]);
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findByVisit(int $visitId): Collection
    {
        try {
            return $this->model
                ->with(['staff', 'credentialSnapshot'])
                ->where('visit_id', $visitId)
                ->orderBy('participation_started_at', 'asc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to find visit actors by visit', [
                'visit_id' => $visitId,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findByStaff(int $staffId, array $filters = []): LengthAwarePaginator
    {
        try {
            $query = $this->model
                ->with(['visit', 'facility'])
                ->where('staff_id', $staffId);

            // Apply filters
            if (!empty($filters['participation_type'])) {
                $query->where('participation_type', $filters['participation_type']);
            }

            if (!empty($filters['is_billable_provider'])) {
                $query->where('is_billable_provider', filter_var($filters['is_billable_provider'], FILTER_VALIDATE_BOOLEAN));
            }

            if (!empty($filters['date_from'])) {
                $query->whereDate('participation_started_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->whereDate('participation_started_at', '<=', $filters['date_to']);
            }

            return $query->orderBy('participation_started_at', 'desc')->paginate($filters['per_page'] ?? 15);
        } catch (\Exception $e) {
            Log::error('Failed to find visit actors by staff', [
                'staff_id' => $staffId,
                'error' => $e->getMessage()
            ]);
            return new LengthAwarePaginator([], 0, $filters['per_page'] ?? 15);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): VisitActor
    {
        try {
            DB::beginTransaction();

            $visitActor = $this->model->create($data);

            // Calculate time involvement if ended time is provided
            if (isset($data['participation_ended_at']) && $visitActor->participation_started_at) {
                $visitActor->time_involvement_minutes = $visitActor->calculateTimeInvolvement();
                $visitActor->save();
            }

            DB::commit();
            return $visitActor->fresh(['visit', 'staff', 'credentialSnapshot']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create visit actor', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(int $id, array $data): VisitActor
    {
        try {
            DB::beginTransaction();

            $visitActor = $this->find($id);
            if (!$visitActor) {
                throw new \Exception("Visit actor not found with ID: {$id}");
            }

            $visitActor->update($data);

            // Recalculate time involvement if ended time changed
            if (isset($data['participation_ended_at']) && $visitActor->participation_started_at) {
                $visitActor->time_involvement_minutes = $visitActor->calculateTimeInvolvement();
                $visitActor->save();
            }

            DB::commit();
            return $visitActor->fresh(['visit', 'staff', 'credentialSnapshot']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update visit actor', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        try {
            $visitActor = $this->find($id);
            if (!$visitActor) {
                return false;
            }

            return $visitActor->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete visit actor', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function endParticipation(int $id, array $data): VisitActor
    {
        try {
            DB::beginTransaction();

            $visitActor = $this->find($id);
            if (!$visitActor) {
                throw new \Exception("Visit actor not found with ID: {$id}");
            }

            // Update participation end time and calculate duration
            $visitActor->update([
                'participation_ended_at' => $data['participation_ended_at'] ?? now(),
                'time_involvement_minutes' => $visitActor->calculateTimeInvolvement(),
            ]);

            // Update metadata if provided
            if (isset($data['metadata'])) {
                $currentMetadata = $visitActor->metadata ?? [];
                $visitActor->metadata = array_merge($currentMetadata, $data['metadata']);
                $visitActor->save();
            }

            DB::commit();
            return $visitActor->fresh(['visit', 'staff', 'credentialSnapshot']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to end participation for visit actor', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveParticipations(int $staffId): Collection
    {
        try {
            return $this->model
                ->with(['visit', 'facility'])
                ->where('staff_id', $staffId)
                ->whereNull('participation_ended_at')
                ->where('participation_started_at', '<=', now())
                ->orderBy('participation_started_at', 'asc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get active participations', [
                'staff_id' => $staffId,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isDuplicateParticipation(
        int $visitId,
        int $staffId,
        string $participationType,
        string $startedAt
    ): bool {
        try {
            return $this->model
                ->where('visit_id', $visitId)
                ->where('staff_id', $staffId)
                ->where('participation_type', $participationType)
                ->where('participation_started_at', $startedAt)
                ->exists();
        } catch (\Exception $e) {
            Log::error('Failed to check duplicate participation', [
                'visit_id' => $visitId,
                'staff_id' => $staffId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}