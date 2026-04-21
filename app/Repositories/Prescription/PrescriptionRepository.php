<?php

declare(strict_types=1);

namespace App\Repositories\Prescription;

use App\Models\Prescription;
use App\Repositories\Contracts\PrescriptionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PrescriptionRepository implements PrescriptionRepositoryInterface
{
    protected Prescription $model;

    public function __construct(Prescription $model)
    {
        $this->model = $model;
    }

    public function all(array $filters = []): Collection
    {
        $query = $this->model->query();
        
        if (!empty($filters['facility_id'])) {
            $query->byFacility($filters['facility_id']);
        }
        
        if (!empty($filters['patient_id'])) {
            $query->byPatient($filters['patient_id']);
        }
        
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->byDateRange($filters['date_from'], $filters['date_to']);
        }
        
        return $query->latest()->get();
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->query();
        
        if (!empty($filters['facility_id'])) {
            $query->byFacility($filters['facility_id']);
        }
        
        if (!empty($filters['patient_id'])) {
            $query->byPatient($filters['patient_id']);
        }
        
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        return $query->with(['patient', 'prescribedBy', 'items'])
                     ->latest()
                     ->paginate($perPage);
    }

    public function find(int $id): ?object
    {
        return $this->model->with(['patient', 'prescribedBy', 'clinicalTemplate'])
                           ->find($id);
    }

    public function findWithItems(int $id): ?object
    {
        return $this->model->with(['items', 'patient', 'prescribedBy', 'clinicalTemplate'])
                           ->find($id);
    }

    public function create(array $data): object
    {
        return DB::transaction(function () use ($data) {
            return $this->model->create($data);
        });
    }

    public function update(int $id, array $data): bool
    {
        $prescription = $this->model->find($id);
        
        if (!$prescription) {
            return false;
        }
        
        return DB::transaction(function () use ($prescription, $data) {
            return $prescription->update($data);
        });
    }

    public function delete(int $id): bool
    {
        $prescription = $this->model->find($id);
        
        if (!$prescription) {
            return false;
        }
        
        return DB::transaction(function () use ($prescription) {
            $prescription->items()->delete();
            return $prescription->delete();
        });
    }

    public function getByPatient(int $patientId, array $statuses = []): Collection
    {
        $query = $this->model->byPatient($patientId);
        
        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        }
        
        return $query->with(['items'])->latest()->get();
    }

    public function getByVisit(int $visitId): Collection
    {
        return $this->model->where('visit_id', $visitId)
                           ->with(['items'])
                           ->get();
    }

    public function getActivePrescriptions(int $patientId): Collection
    {
        return $this->model->byPatient($patientId)
                           ->active()
                           ->where(function ($q) {
                               $q->whereNull('valid_until')
                                 ->orWhere('valid_until', '>=', now());
                           })
                           ->with(['items'])
                           ->get();
    }

    public function getReadyForBilling(int $patientId): Collection
    {
        return $this->model->byPatient($patientId)
                           ->readyForBilling()
                           ->with(['items'])
                           ->get();
    }

    public function updateStatus(int $id, string $status): bool
    {
        return $this->update($id, ['status' => $status]);
    }

    public function markAsDispensed(int $id, array $dispensingData): bool
    {
        $prescription = $this->model->find($id);
        
        if (!$prescription) {
            return false;
        }
        
        $prescription->status = 'Fully Dispensed';
        $prescription->dispensed_at = now();
        $prescription->dispensed_pharmacy = $dispensingData['pharmacy_name'] ?? null;
        $prescription->dispensed_by_name = $dispensingData['dispensed_by_name'] ?? null;
        $prescription->dispensing_location = isset($dispensingData['pharmacy_name']) 
            ? 'Dispensed at External Pharmacy' 
            : 'Dispensed at Our Facility';
        
        return $prescription->save();
    }

    public function cancel(int $id, string $reason, int $cancelledBy, ?string $notes = null): bool
    {
        $prescription = $this->model->find($id);
        
        if (!$prescription) {
            return false;
        }
        
        $prescription->status = 'Cancelled - No Longer Valid';
        $prescription->cancelled_at = now();
        $prescription->cancelled_by = $cancelledBy;
        $prescription->cancellation_reason = $reason;
        $prescription->cancellation_notes = $notes;
        
        return $prescription->save();
    }

  public function generatePrescriptionNumber(int $facilityId): string
{
    $year = now()->format('Y');
    $month = now()->format('m');
    
    // Use database atomic increment
    $sequence = \App\Models\PrescriptionSequence::where('facility_id', $facilityId)
        ->where('year', $year)
        ->where('month', $month)
        ->lockForUpdate()
        ->first();
    
    if (!$sequence) {
        $sequence = \App\Models\PrescriptionSequence::create([
            'facility_id' => $facilityId,
            'year' => $year,
            'month' => $month,
            'last_number' => 0,
        ]);
    }
    
    // Increment atomically
    $sequence->increment('last_number');
    $sequence->refresh();
    
    // Cast to string to avoid str_pad type error
    $sequenceNumber = str_pad((string) $sequence->last_number, 6, '0', STR_PAD_LEFT);
    
    return "RX-{$year}{$month}-{$sequenceNumber}";
}
}