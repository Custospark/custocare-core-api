<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ClinicalNote;
use App\Repositories\Contracts\ClinicalNoteRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ClinicalNoteRepository implements ClinicalNoteRepositoryInterface
{
    /**
     * @var ClinicalNote
     */
    protected ClinicalNote $model;

    /**
     * Constructor.
     *
     * @param ClinicalNote $model
     */
    public function __construct(ClinicalNote $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?ClinicalNote
    {
        return $this->model->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findByIdWithRelations(int $id, array $relations = []): ?ClinicalNote
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

        if (!empty($filters['note_type'])) {
            $query->ofType($filters['note_type']);
        }

        if (!empty($filters['note_status'])) {
            $query->withStatus($filters['note_status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('noted_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('noted_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('subjective', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('objective', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('assessment', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('plan', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Apply sorting
        $orderBy = $filters['order_by'] ?? 'noted_at';
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

        if (!empty($filters['note_type'])) {
            $query->ofType($filters['note_type']);
        }

        if (!empty($filters['note_status'])) {
            $query->withStatus($filters['note_status']);
        }

        if (!empty($filters['limit'])) {
            $query->limit($filters['limit']);
        }

        return $query->orderBy('noted_at', 'desc')->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getPaginatedByPatient(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->forPatient($patientId);

        if (!empty($filters['note_type'])) {
            $query->ofType($filters['note_type']);
        }

        if (!empty($filters['note_status'])) {
            $query->withStatus($filters['note_status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('noted_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('noted_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('noted_at', 'desc')->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getByVisit(int $visitId): Collection
    {
        return $this->model->forVisit($visitId)
            ->orderBy('noted_at', 'desc')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByFacility(int $facilityId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->forFacility($facilityId);

        if (!empty($filters['note_type'])) {
            $query->ofType($filters['note_type']);
        }

        if (!empty($filters['note_status'])) {
            $query->withStatus($filters['note_status']);
        }

        if (!empty($filters['patient_id'])) {
            $query->forPatient($filters['patient_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('noted_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('noted_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('noted_at', 'desc')->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getFinalNotesByPatient(int $patientId, int $limit = 10): Collection
    {
        return $this->model->forPatient($patientId)
            ->final()
            ->orderBy('noted_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getNoteHistory(int $noteId): Collection
    {
        $note = $this->findById($noteId);

        if (!$note) {
            return new Collection();
        }

        // Get the original note and all its amendments
        $originalId = $note->parent_note_id ?? $noteId;

        return $this->model->where(function ($query) use ($originalId, $noteId) {
            $query->where('id', $originalId)
                ->orWhere('parent_note_id', $originalId);
        })->orderBy('noted_at', 'asc')->get();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): ClinicalNote
    {
        return DB::transaction(function () use ($data) {
            return $this->model->create($data);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function update(ClinicalNote $note, array $data): bool
    {
        return DB::transaction(function () use ($note, $data) {
            return $note->update($data);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function delete(ClinicalNote $note): bool
    {
        return DB::transaction(function () use ($note) {
            return $note->delete();
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
    public function updateStatus(ClinicalNote $note, string $status): bool
    {
        return DB::transaction(function () use ($note, $status) {
            return $note->update(['note_status' => $status]);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getByDateRange(int $patientId, string $startDate, string $endDate): Collection
    {
        return $this->model->forPatient($patientId)
            ->whereBetween('noted_at', [$startDate, $endDate])
            ->orderBy('noted_at', 'asc')
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function searchNotes(string $searchTerm, ?int $facilityId = null, int $limit = 20): Collection
    {
        $query = $this->model->query();

        if ($facilityId) {
            $query->forFacility($facilityId);
        }

        return $query->where(function ($q) use ($searchTerm) {
                $q->where('subjective', 'like', '%' . $searchTerm . '%')
                    ->orWhere('objective', 'like', '%' . $searchTerm . '%')
                    ->orWhere('assessment', 'like', '%' . $searchTerm . '%')
                    ->orWhere('plan', 'like', '%' . $searchTerm . '%');
            })
            ->limit($limit)
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getNoteCountByStatus(int $facilityId): array
    {
        $counts = $this->model->forFacility($facilityId)
            ->select('note_status', DB::raw('count(*) as total'))
            ->groupBy('note_status')
            ->pluck('total', 'note_status')
            ->toArray();

        return [
            'draft' => $counts['draft'] ?? 0,
            'final' => $counts['final'] ?? 0,
            'amended' => $counts['amended'] ?? 0,
            'cancelled' => $counts['cancelled'] ?? 0,
            'total' => array_sum($counts),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getLatestNote(int $patientId): ?ClinicalNote
    {
        return $this->model->forPatient($patientId)
            ->orderBy('noted_at', 'desc')
            ->first();
    }
}