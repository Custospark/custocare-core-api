<?php

namespace App\Repositories\ClinicalDocument;

use App\Models\ClinicalDocument;
use App\Repositories\Contracts\ClinicalDocumentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ClinicalDocumentRepository implements ClinicalDocumentRepositoryInterface
{
    /**
     * @var ClinicalDocument
     */
    protected $model;

    /**
     * Constructor
     *
     * @param ClinicalDocument $model
     */
    public function __construct(ClinicalDocument $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritDoc}
     */
    public function find(int $id): ?ClinicalDocument
    {
        return $this->model->find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function findByUuid(string $uuid): ?ClinicalDocument
    {
        return $this->model->where('document_uuid', $uuid)->first();
    }

    /**
     * {@inheritDoc}
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->with(['patient', 'visit', 'facility']);

        // Apply filters
        if (!empty($filters['patient_id'])) {
            $query->where('patient_id', $filters['patient_id']);
        }

        if (!empty($filters['facility_id'])) {
            $query->where('facility_id', $filters['facility_id']);
        }

        if (!empty($filters['visit_id'])) {
            $query->where('visit_id', $filters['visit_id']);
        }

        if (!empty($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('document_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('document_date', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('document_name', 'LIKE', "%{$search}%")
                  ->orWhere('document_description', 'LIKE', "%{$search}%");
            });
        }

        // Order by latest first
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function getByPatientId(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->with(['visit', 'facility'])
            ->where('patient_id', $patientId);

        // Apply additional filters
        if (!empty($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['visit_id'])) {
            $query->where('visit_id', $filters['visit_id']);
        }

        $query->orderBy('document_date', 'desc')
              ->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): ClinicalDocument
    {
        return DB::transaction(function () use ($data) {
            return $this->model->create($data);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function update(int $id, array $data): ClinicalDocument
    {
        return DB::transaction(function () use ($id, $data) {
            $document = $this->find($id);
            
            if (!$document) {
                throw new \RuntimeException("Clinical document with ID {$id} not found");
            }

            $document->update($data);
            
            return $document->fresh();
        });
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $id): bool
    {
        $document = $this->find($id);
        
        if (!$document) {
            return false;
        }

        return $document->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function forceDelete(int $id): bool
    {
        $document = $this->model->withTrashed()->find($id);
        
        if (!$document) {
            return false;
        }

        return $document->forceDelete();
    }

    /**
     * {@inheritDoc}
     */
    public function restore(int $id): bool
    {
        $document = $this->model->withTrashed()->find($id);
        
        if (!$document) {
            return false;
        }

        return $document->restore();
    }

    /**
     * {@inheritDoc}
     */
    public function updateStatus(int $id, string $status): bool
    {
        $document = $this->find($id);
        
        if (!$document) {
            return false;
        }

        $document->status = $status;
        return $document->save();
    }

    /**
     * {@inheritDoc}
     */
    public function fileHashExists(string $fileHash, ?int $patientId = null): bool
    {
        $query = $this->model->where('file_hash', $fileHash);
        
        if ($patientId) {
            $query->where('patient_id', $patientId);
        }

        return $query->exists();
    }
}