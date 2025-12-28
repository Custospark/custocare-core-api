<?php

namespace App\Repositories\PatientVisitSummaryView;

use App\Models\PatientVisitSummaryView;
use App\Repositories\Contracts\PatientVisitSummaryViewRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientVisitSummaryViewRepository implements PatientVisitSummaryViewRepositoryInterface
{
    /**
     * PatientVisitSummaryView model instance.
     *
     * @var PatientVisitSummaryView
     */
    protected PatientVisitSummaryView $model;

    /**
     * Constructor.
     *
     * @param PatientVisitSummaryView $model
     */
    public function __construct(PatientVisitSummaryView $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?PatientVisitSummaryView
    {
        try {
            return $this->model->with(['patient', 'lastVisitFacility', 'primaryCareProvider'])->find($id);
        } catch (\Exception $e) {
            Log::error('Failed to find patient visit summary by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findByPatientId(int $patientId): ?PatientVisitSummaryView
    {
        try {
            return $this->model->with(['patient', 'lastVisitFacility', 'primaryCareProvider'])
                ->where('patient_id', $patientId)
                ->first();
        } catch (\Exception $e) {
            Log::error('Failed to find patient visit summary by patient ID', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        try {
            $query = $this->model->with(['patient', 'lastVisitFacility', 'primaryCareProvider']);

            // Apply filters
            if (!empty($filters['patient_id'])) {
                $query->where('patient_id', $filters['patient_id']);
            }

            if (!empty($filters['has_upcoming_appointments'])) {
                $query->whereNotNull('next_appointment_at')
                    ->where('next_appointment_at', '>', now());
            }

            if (!empty($filters['has_outstanding_bills'])) {
                $query->where('outstanding_bills_total', '>', 0);
            }

            if (!empty($filters['last_updated_since'])) {
                $query->where('last_updated_at', '>=', $filters['last_updated_since']);
            }

            if (!empty($filters['search'])) {
                $query->whereHas('patient', function ($q) use ($filters) {
                    $q->where('first_name', 'like', "%{$filters['search']}%")
                        ->orWhere('last_name', 'like', "%{$filters['search']}%")
                        ->orWhere('email', 'like', "%{$filters['search']}%");
                });
            }

            // Apply sorting
            $sortBy = $filters['sort_by'] ?? 'last_updated_at';
            $sortOrder = $filters['sort_order'] ?? 'desc';
            $query->orderBy($sortBy, $sortOrder);

            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to paginate patient visit summaries', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);
            return $this->model->paginate($perPage);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): PatientVisitSummaryView
    {
        try {
            DB::beginTransaction();

            $summary = $this->model->create([
                ...$data,
                'last_updated_at' => now(),
            ]);

            DB::commit();
            return $summary->load(['patient', 'lastVisitFacility', 'primaryCareProvider']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create patient visit summary', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function update(int $id, array $data): PatientVisitSummaryView
    {
        try {
            DB::beginTransaction();

            $summary = $this->findById($id);
            if (!$summary) {
                throw new \Exception("Patient visit summary not found with ID: {$id}");
            }

            $summary->update([
                ...$data,
                'last_updated_at' => now(),
            ]);

            DB::commit();
            return $summary->load(['patient', 'lastVisitFacility', 'primaryCareProvider']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update patient visit summary', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateOrCreateByPatientId(int $patientId, array $data): PatientVisitSummaryView
    {
        try {
            DB::beginTransaction();

            $summary = $this->model->updateOrCreate(
                ['patient_id' => $patientId],
                [
                    ...$data,
                    'last_updated_at' => now(),
                ]
            );

            DB::commit();
            return $summary->load(['patient', 'lastVisitFacility', 'primaryCareProvider']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update or create patient visit summary by patient ID', [
                'patient_id' => $patientId,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $id): bool
    {
        try {
            DB::beginTransaction();

            $summary = $this->findById($id);
            if (!$summary) {
                throw new \Exception("Patient visit summary not found with ID: {$id}");
            }

            $deleted = $summary->delete();

            DB::commit();
            return $deleted;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete patient visit summary', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getWithUpcomingAppointments(string $startDate, string $endDate): Collection
    {
        try {
            return $this->model->with(['patient', 'lastVisitFacility', 'primaryCareProvider'])
                ->whereNotNull('next_appointment_at')
                ->whereBetween('next_appointment_at', [$startDate, $endDate])
                ->orderBy('next_appointment_at')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get summaries with upcoming appointments', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getByLastUpdatedDate(string $date): Collection
    {
        try {
            return $this->model->with(['patient', 'lastVisitFacility', 'primaryCareProvider'])
                ->whereDate('last_updated_at', $date)
                ->orderBy('last_updated_at', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get summaries by last updated date', [
                'date' => $date,
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getOutdatedPatientIds(int $hoursThreshold = 24): array
    {
        try {
            $cutoffTime = now()->subHours($hoursThreshold);

            return $this->model
                ->where('last_updated_at', '<', $cutoffTime)
                ->orWhereNull('last_updated_at')
                ->pluck('patient_id')
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to get outdated patient IDs', [
                'hours_threshold' => $hoursThreshold,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}