<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Diagnosis;
use App\Repositories\Contracts\DiagnosisRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DiagnosisRepository implements DiagnosisRepositoryInterface
{
    /**
     * @var Diagnosis
     */
    protected Diagnosis $model;

    /**
     * Constructor.
     *
     * @param Diagnosis $model
     */
    public function __construct(Diagnosis $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Diagnosis
    {
        return $this->model->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findByIdWithRelations(int $id, array $relations = []): ?Diagnosis
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
            $query->where('visit_id', $filters['visit_id']);
        }

        if (!empty($filters['diagnosis_type'])) {
            $query->ofType($filters['diagnosis_type']);
        }

        if (!empty($filters['clinical_status'])) {
            $query->where('clinical_status', $filters['clinical_status']);
        }

        if (!empty($filters['certainty'])) {
            $query->withCertainty($filters['certainty']);
        }

        if (!empty($filters['verification_status'])) {
            $query->where('verification_status', $filters['verification_status']);
        }

        if (!empty($filters['diagnosis_code'])) {
            $query->where('diagnosis_code', 'like', '%' . $filters['diagnosis_code'] . '%');
        }

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Apply sorting
        $orderBy = $filters['order_by'] ?? 'created_at';
        $orderDirection = $filters['order_direction'] ?? 'desc';
        $query->orderBy($orderBy, $orderDirection);

        return $query->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getByPatient(int $patientId, array $filters = []): Collection
    {
        $query = $this->model->forPatient($patientId);

        if (!empty($filters['diagnosis_type'])) {
            $query->ofType($filters['diagnosis_type']);
        }

        if (!empty($filters['clinical_status'])) {
            $query->where('clinical_status', $filters['clinical_status']);
        }

        if (!empty($filters['limit'])) {
            $query->limit($filters['limit']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveByPatient(int $patientId): Collection
    {
        return $this->model->forPatient($patientId)
            ->active()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryByPatient(int $patientId): Collection
    {
        return $this->model->forPatient($patientId)
            ->primary()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByVisit(int $visitId): Collection
    {
        return $this->model->where('visit_id', $visitId)
            ->orderBy('diagnosis_type', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByFacility(int $facilityId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->forFacility($facilityId);

        if (!empty($filters['diagnosis_type'])) {
            $query->ofType($filters['diagnosis_type']);
        }

        if (!empty($filters['clinical_status'])) {
            $query->where('clinical_status', $filters['clinical_status']);
        }

        if (!empty($filters['patient_id'])) {
            $query->forPatient($filters['patient_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getByCode(string $code, ?int $facilityId = null): Collection
    {
        $query = $this->model->where('diagnosis_code', $code);

        if ($facilityId) {
            $query->forFacility($facilityId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getVerifiedDiagnoses(?int $facilityId = null, int $limit = 50): Collection
    {
        $query = $this->model->verified();

        if ($facilityId) {
            $query->forFacility($facilityId);
        }

        return $query->orderBy('verified_at', 'desc')->limit($limit)->get();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): Diagnosis
    {
        return DB::transaction(function () use ($data) {
            return $this->model->create($data);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(Diagnosis $diagnosis, array $data): bool
    {
        return DB::transaction(function () use ($diagnosis, $data) {
            return $diagnosis->update($data);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Diagnosis $diagnosis): bool
    {
        return DB::transaction(function () use ($diagnosis) {
            return $diagnosis->delete();
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
    public function updateVerificationStatus(Diagnosis $diagnosis, string $status, ?int $verifiedByStaffId = null): bool
    {
        $data = ['verification_status' => $status];

        if ($status === 'verified') {
            $data['verified_at'] = now();
            if ($verifiedByStaffId) {
                $data['verified_by'] = $verifiedByStaffId;
            }
        }

        return DB::transaction(function () use ($diagnosis, $data) {
            return $diagnosis->update($data);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getCountByType(int $patientId): array
    {
        $counts = $this->model->forPatient($patientId)
            ->select('diagnosis_type', DB::raw('count(*) as total'))
            ->groupBy('diagnosis_type')
            ->pluck('total', 'diagnosis_type')
            ->toArray();

        return [
            'primary' => $counts['primary'] ?? 0,
            'secondary' => $counts['secondary'] ?? 0,
            'differential' => $counts['differential'] ?? 0,
            'admitting' => $counts['admitting'] ?? 0,
            'discharge' => $counts['discharge'] ?? 0,
            'provisional' => $counts['provisional'] ?? 0,
            'total' => array_sum($counts),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getMostCommonDiagnoses(int $facilityId, int $limit = 10): Collection
    {
        return $this->model->forFacility($facilityId)
            ->select('diagnosis_code', 'diagnosis_description', DB::raw('count(*) as occurrence_count'))
            ->groupBy('diagnosis_code', 'diagnosis_description')
            ->orderBy('occurrence_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function searchDiagnoses(string $searchTerm, ?int $facilityId = null, int $limit = 20): Collection
    {
        $query = $this->model->search($searchTerm);

        if ($facilityId) {
            $query->forFacility($facilityId);
        }

        return $query->limit($limit)->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByDateRange(int $patientId, string $startDate, string $endDate): Collection
    {
        return $this->model->forPatient($patientId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'asc')
            ->get();
    }
}