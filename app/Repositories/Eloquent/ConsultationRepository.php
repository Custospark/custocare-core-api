<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Consultation;
use App\Repositories\Contracts\ConsultationRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ConsultationRepository implements ConsultationRepositoryInterface
{
    /**
     * @var Consultation
     */
    protected Consultation $model;

    /**
     * Constructor.
     *
     * @param Consultation $model
     */
    public function __construct(Consultation $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Consultation
    {
        return $this->model->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findByIdWithRelations(int $id, array $relations = []): ?Consultation
    {
        return $this->model->with($relations)->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Apply filters
        if (!empty($filters['facility_id'])) {
            $query->forFacility($filters['facility_id']);
        }

        if (!empty($filters['patient_id'])) {
            $query->forPatient($filters['patient_id']);
        }

        if (!empty($filters['visit_id'])) {
            $query->forVisit($filters['visit_id']);
        }

        if (!empty($filters['request_status'])) {
            $query->withStatus($filters['request_status']);
        }

        if (!empty($filters['priority'])) {
            $query->withPriority($filters['priority']);
        }

        if (!empty($filters['consultation_type'])) {
            $query->where('consultation_type', $filters['consultation_type']);
        }

        if (!empty($filters['specialty_required'])) {
            $query->forSpecialty($filters['specialty_required']);
        }

        if (!empty($filters['consultant_staff_id'])) {
            $query->forConsultant($filters['consultant_staff_id']);
        }

        if (!empty($filters['requesting_staff_id'])) {
            $query->requestedBy($filters['requesting_staff_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('requested_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('requested_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['scheduled_from'])) {
            $query->whereDate('scheduled_for', '>=', $filters['scheduled_from']);
        }

        if (!empty($filters['scheduled_to'])) {
            $query->whereDate('scheduled_for', '<=', $filters['scheduled_to']);
        }

        // Apply sorting
        $orderBy = $filters['order_by'] ?? 'requested_at';
        $orderDirection = $filters['order_direction'] ?? 'desc';
        $query->orderBy($orderBy, $orderDirection);

        return $query->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getByPatient(int $patientId, array $filters = [], int $limit = 50): Collection
    {
        $query = $this->model->forPatient($patientId);

        if (!empty($filters['request_status'])) {
            $query->withStatus($filters['request_status']);
        }

        if (!empty($filters['priority'])) {
            $query->withPriority($filters['priority']);
        }

        return $query->orderBy('requested_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getPaginatedByPatient(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->forPatient($patientId);

        if (!empty($filters['request_status'])) {
            $query->withStatus($filters['request_status']);
        }

        if (!empty($filters['priority'])) {
            $query->withPriority($filters['priority']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('requested_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('requested_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('requested_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getByVisit(int $visitId): Collection
    {
        return $this->model->forVisit($visitId)
            ->orderBy('requested_at', 'desc')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByFacility(int $facilityId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->forFacility($facilityId);

        if (!empty($filters['request_status'])) {
            $query->withStatus($filters['request_status']);
        }

        if (!empty($filters['priority'])) {
            $query->withPriority($filters['priority']);
        }

        if (!empty($filters['patient_id'])) {
            $query->forPatient($filters['patient_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('requested_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('requested_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('requested_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getByConsultant(int $consultantStaffId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->forConsultant($consultantStaffId);

        if (!empty($filters['request_status'])) {
            $query->withStatus($filters['request_status']);
        }

        if (!empty($filters['priority'])) {
            $query->withPriority($filters['priority']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('requested_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('requested_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('requested_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getPendingConsultations(?int $facilityId = null, int $limit = 50): Collection
    {
        $query = $this->model->pending();

        if ($facilityId) {
            $query->forFacility($facilityId);
        }

        return $query->orderBy('priority', 'asc')
            ->orderBy('requested_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getUrgentConsultations(?int $facilityId = null, int $limit = 50): Collection
    {
        $query = $this->model->urgent();

        if ($facilityId) {
            $query->forFacility($facilityId);
        }

        return $query->orderBy('priority', 'desc')
            ->orderBy('requested_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getOverdueConsultations(?int $facilityId = null, int $limit = 50): Collection
    {
        $query = $this->model->where(function ($q) {
            $q->where('request_status', 'pending')
                ->where('requested_at', '<', now()->subHours(48));
        })->orWhere(function ($q) {
            $q->where('request_status', 'accepted')
                ->where('scheduled_for', '<', now());
        });

        if ($facilityId) {
            $query->forFacility($facilityId);
        }

        return $query->orderBy('requested_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): Consultation
    {
        return DB::transaction(function () use ($data) {
            return $this->model->create($data);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(Consultation $consultation, array $data): bool
    {
        return DB::transaction(function () use ($consultation, $data) {
            return $consultation->update($data);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Consultation $consultation): bool
    {
        return DB::transaction(function () use ($consultation) {
            return $consultation->delete();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function restore(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            return $this->model->withTrashed()->find($id)?->restore() ?? false;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function forceDelete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            return $this->model->withTrashed()->find($id)?->forceDelete() ?? false;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function updateStatus(Consultation $consultation, string $status): bool
    {
        return DB::transaction(function () use ($consultation, $status) {
            return $consultation->update(['request_status' => $status]);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getCountByStatus(int $facilityId): array
    {
        $counts = $this->model->forFacility($facilityId)
            ->select('request_status', DB::raw('count(*) as total'))
            ->groupBy('request_status')
            ->pluck('total', 'request_status')
            ->toArray();

        return [
            'pending' => $counts['pending'] ?? 0,
            'accepted' => $counts['accepted'] ?? 0,
            'declined' => $counts['declined'] ?? 0,
            'completed' => $counts['completed'] ?? 0,
            'cancelled' => $counts['cancelled'] ?? 0,
            'total' => array_sum($counts),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getBySpecialty(string $specialty, ?int $facilityId = null, int $limit = 50): Collection
    {
        $query = $this->model->forSpecialty($specialty);

        if ($facilityId) {
            $query->forFacility($facilityId);
        }

        return $query->orderBy('requested_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getConsultationStatistics(int $facilityId, string $startDate, string $endDate): array
    {
        $consultations = $this->model->forFacility($facilityId)
            ->whereBetween('requested_at', [$startDate, $endDate])
            ->get();

        return [
            'total_requests' => $consultations->count(),
            'pending_count' => $consultations->where('request_status', 'pending')->count(),
            'accepted_count' => $consultations->where('request_status', 'accepted')->count(),
            'declined_count' => $consultations->where('request_status', 'declined')->count(),
            'completed_count' => $consultations->where('request_status', 'completed')->count(),
            'cancelled_count' => $consultations->where('request_status', 'cancelled')->count(),
            'urgent_count' => $consultations->whereIn('priority', ['urgent', 'emergent'])->count(),
            'routine_count' => $consultations->where('priority', 'routine')->count(),
            'average_response_time' => $consultations->filter(fn($c) => $c->responded_at)
                ->avg(fn($c) => $c->requested_at->diffInHours($c->responded_at)),
            'completion_rate' => $consultations->count() > 0
                ? round(($consultations->where('request_status', 'completed')->count() / $consultations->count()) * 100, 2)
                : 0,
        ];
    }
}