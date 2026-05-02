<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Vital;
use App\Repositories\Contracts\VitalRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class VitalRepository implements VitalRepositoryInterface
{
    /**
     * @var Vital
     */
    protected Vital $model;

    /**
     * Constructor.
     *
     * @param Vital $model
     */
    public function __construct(Vital $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?Vital
    {
        return $this->model->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findByIdWithRelations(int $id, array $relations = []): ?Vital
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

        if (!empty($filters['staff_id'])) {
            $query->where('staff_id', $filters['staff_id']);
        }

        if (!empty($filters['consciousness_level'])) {
            $query->where('consciousness_level', $filters['consciousness_level']);
        }

        if (!empty($filters['abnormal_only'])) {
            $query->abnormal();
        }

        if (!empty($filters['critical_only'])) {
            $query->critical();
        }

        if (!empty($filters['date_from'])) {
            $query->measuredAfter($filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->measuredBefore($filters['date_to']);
        }

        // Apply sorting
        $orderBy = $filters['order_by'] ?? 'measured_at';
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

        if (!empty($filters['date_from'])) {
            $query->measuredAfter($filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->measuredBefore($filters['date_to']);
        }

        return $query->orderBy('measured_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getPaginatedByPatient(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->forPatient($patientId);

        if (!empty($filters['date_from'])) {
            $query->measuredAfter($filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->measuredBefore($filters['date_to']);
        }

        if (!empty($filters['abnormal_only'])) {
            $query->abnormal();
        }

        return $query->orderBy('measured_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getByVisit(int $visitId): Collection
    {
        return $this->model->forVisit($visitId)
            ->orderBy('measured_at', 'desc')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getLatestByPatient(int $patientId): ?Vital
    {
        return $this->model->forPatient($patientId)
            ->orderBy('measured_at', 'desc')
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getByDateRange(int $patientId, string $startDate, string $endDate): Collection
    {
        return $this->model->forPatient($patientId)
            ->measuredBetween($startDate, $endDate)
            ->orderBy('measured_at', 'asc')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getAbnormalVitals(?int $facilityId = null, int $limit = 50): Collection
    {
        $query = $this->model->abnormal();

        if ($facilityId) {
            $query->forFacility($facilityId);
        }

        return $query->orderBy('measured_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getCriticalVitals(?int $facilityId = null, int $limit = 50): Collection
    {
        $query = $this->model->critical();

        if ($facilityId) {
            $query->forFacility($facilityId);
        }

        return $query->orderBy('measured_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): Vital
    {
        return DB::transaction(function () use ($data) {
            return $this->model->create($data);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(Vital $vital, array $data): bool
    {
        return DB::transaction(function () use ($vital, $data) {
            return $vital->update($data);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Vital $vital): bool
    {
        return DB::transaction(function () use ($vital) {
            return $vital->delete();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getVitalTrend(int $patientId, string $vitalType, int $limit = 10): Collection
    {
        $validVitalTypes = ['temperature', 'heart_rate', 'respiratory_rate', 'systolic_bp', 'diastolic_bp', 'oxygen_saturation', 'pain_score', 'bmi'];

        if (!in_array($vitalType, $validVitalTypes)) {
            return new Collection();
        }

        return $this->model->forPatient($patientId)
            ->whereNotNull($vitalType)
            ->orderBy('measured_at', 'desc')
            ->limit($limit)
            ->get([$vitalType, 'measured_at', 'id']);
    }

    /**
     * {@inheritdoc}
     */
    public function getVitalStatistics(int $facilityId, string $startDate, string $endDate): array
    {
        $vitals = $this->model->forFacility($facilityId)
            ->measuredBetween($startDate, $endDate)
            ->get();

        $stats = [
            'total_measurements' => $vitals->count(),
            'abnormal_count' => $vitals->filter(fn($v) => !empty($v->flag_status) && isset($v->flag_status['warning']))->count(),
            'critical_count' => $vitals->filter(fn($v) => !empty($v->clinical_alert))->count(),
            'average_temperature' => $vitals->avg('temperature'),
            'average_heart_rate' => $vitals->avg('heart_rate'),
            'average_respiratory_rate' => $vitals->avg('respiratory_rate'),
            'average_systolic_bp' => $vitals->avg('systolic_bp'),
            'average_diastolic_bp' => $vitals->avg('diastolic_bp'),
            'average_oxygen_saturation' => $vitals->avg('oxygen_saturation'),
            'average_pain_score' => $vitals->avg('pain_score'),
            'average_bmi' => $vitals->avg('bmi'),
        ];

        return $stats;
    }

    /**
     * {@inheritdoc}
     */
    public function getAverageVitals(int $patientId, string $startDate, string $endDate): array
    {
        $vitals = $this->model->forPatient($patientId)
            ->measuredBetween($startDate, $endDate)
            ->get();

        return [
            'average_temperature' => $vitals->avg('temperature'),
            'average_heart_rate' => $vitals->avg('heart_rate'),
            'average_respiratory_rate' => $vitals->avg('respiratory_rate'),
            'average_systolic_bp' => $vitals->avg('systolic_bp'),
            'average_diastolic_bp' => $vitals->avg('diastolic_bp'),
            'average_oxygen_saturation' => $vitals->avg('oxygen_saturation'),
            'average_pain_score' => $vitals->avg('pain_score'),
            'average_bmi' => $vitals->avg('bmi'),
            'measurement_count' => $vitals->count(),
        ];
    }
}