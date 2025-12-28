<?php

namespace App\Repositories\BillingCycle;

use App\Models\BillingCycle;
use App\Repositories\Contracts\BillingCycleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingCycleRepository implements BillingCycleRepositoryInterface
{
    /**
     * Find billing cycle by UUID
     *
     * @param string $uuid
     * @return BillingCycle|null
     */
    public function findByUuid(string $uuid): ?BillingCycle
    {
        try {
            return BillingCycle::where('billing_cycle_uuid', $uuid)->first();
        } catch (\Exception $e) {
            Log::error('Failed to find billing cycle by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find billing cycle by ID
     *
     * @param int $id
     * @return BillingCycle|null
     */
    public function findById(int $id): ?BillingCycle
    {
        try {
            return BillingCycle::find($id);
        } catch (\Exception $e) {
            Log::error('Failed to find billing cycle by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all billing cycles with pagination
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        try {
            $query = BillingCycle::with(['patient', 'visit', 'facility']);

            // Apply filters
            if (!empty($filters['facility_id'])) {
                $query->where('facility_id', $filters['facility_id']);
            }

            if (!empty($filters['patient_id'])) {
                $query->where('patient_id', $filters['patient_id']);
            }

            if (!empty($filters['visit_id'])) {
                $query->where('visit_id', $filters['visit_id']);
            }

            if (!empty($filters['billing_status'])) {
                $query->where('billing_status', $filters['billing_status']);
            }

            if (!empty($filters['cycle_type'])) {
                $query->where('cycle_type', $filters['cycle_type']);
            }

            if (!empty($filters['period_start_from'])) {
                $query->where('period_start', '>=', $filters['period_start_from']);
            }

            if (!empty($filters['period_start_to'])) {
                $query->where('period_start', '<=', $filters['period_start_to']);
            }

            if (!empty($filters['search'])) {
                $searchTerm = $filters['search'];
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('primary_insurance_claim_number', 'like', "%{$searchTerm}%")
                        ->orWhereHas('patient', function ($q) use ($searchTerm) {
                            $q->where('first_name', 'like', "%{$searchTerm}%")
                                ->orWhere('last_name', 'like', "%{$searchTerm}%");
                        });
                });
            }

            // Ordering
            $orderBy = $filters['order_by'] ?? 'created_at';
            $orderDirection = $filters['order_direction'] ?? 'desc';
            $query->orderBy($orderBy, $orderDirection);

            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get paginated billing cycles', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Get billing cycles by facility
     *
     * @param int $facilityId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByFacility(int $facilityId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        try {
            $filters['facility_id'] = $facilityId;
            return $this->getAllPaginated($filters, $perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get billing cycles by facility', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Get billing cycles by patient
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByPatient(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        try {
            $filters['patient_id'] = $patientId;
            return $this->getAllPaginated($filters, $perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get billing cycles by patient', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Get billing cycles by visit
     *
     * @param int $visitId
     * @param array $filters
     * @return Collection
     */
    public function getByVisit(int $visitId, array $filters = []): Collection
    {
        try {
            $query = BillingCycle::where('visit_id', $visitId)
                ->with(['patient', 'facility']);

            if (!empty($filters['billing_status'])) {
                $query->where('billing_status', $filters['billing_status']);
            }

            if (!empty($filters['cycle_type'])) {
                $query->where('cycle_type', $filters['cycle_type']);
            }

            return $query->get();
        } catch (\Exception $e) {
            Log::error('Failed to get billing cycles by visit', [
                'visit_id' => $visitId,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * Get overdue billing cycles
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getOverdue(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        try {
            $query = BillingCycle::overdue()
                ->with(['patient', 'facility', 'visit']);

            // Apply additional filters
            if (!empty($filters['facility_id'])) {
                $query->where('facility_id', $filters['facility_id']);
            }

            if (!empty($filters['patient_id'])) {
                $query->where('patient_id', $filters['patient_id']);
            }

            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get overdue billing cycles', [
                'error' => $e->getMessage()
            ]);
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Get disputed billing cycles
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getDisputed(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        try {
            $query = BillingCycle::disputed()
                ->with(['patient', 'facility', 'visit']);

            // Apply additional filters
            if (!empty($filters['facility_id'])) {
                $query->where('facility_id', $filters['facility_id']);
            }

            if (!empty($filters['patient_id'])) {
                $query->where('patient_id', $filters['patient_id']);
            }

            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get disputed billing cycles', [
                'error' => $e->getMessage()
            ]);
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Create a new billing cycle
     *
     * @param array $data
     * @return BillingCycle
     * @throws \RuntimeException
     */
    public function create(array $data): BillingCycle
    {
        DB::beginTransaction();
        
        try {
            // Generate UUID if not provided
            if (!isset($data['billing_cycle_uuid'])) {
                $data['billing_cycle_uuid'] = \Illuminate\Support\Str::uuid()->toString();
            }

            // Calculate net amount
            if (isset($data['total_amount_charged']) || isset($data['total_adjustments'])) {
                $totalAmountCharged = $data['total_amount_charged'] ?? 0;
                $totalAdjustments = $data['total_adjustments'] ?? 0;
                $data['net_amount'] = max(0, $totalAmountCharged - $totalAdjustments);
            }

            // Calculate days in cycle if period dates are provided
            if (isset($data['period_start']) && isset($data['period_end'])) {
                $start = \Carbon\Carbon::parse($data['period_start']);
                $end = \Carbon\Carbon::parse($data['period_end']);
                $data['days_in_cycle'] = $start->diffInDays($end);
            }

            $billingCycle = BillingCycle::create($data);
            
            DB::commit();
            
            // Reload with relationships
            return $billingCycle->load(['patient', 'visit', 'facility']);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create billing cycle', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Failed to create billing cycle: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing billing cycle
     *
     * @param BillingCycle $billingCycle
     * @param array $data
     * @return BillingCycle
     * @throws \RuntimeException
     */
    public function update(BillingCycle $billingCycle, array $data): BillingCycle
    {
        DB::beginTransaction();
        
        try {
            // Recalculate net amount if financial fields are updated
            if (isset($data['total_amount_charged']) || isset($data['total_adjustments'])) {
                $totalAmountCharged = $data['total_amount_charged'] ?? $billingCycle->total_amount_charged;
                $totalAdjustments = $data['total_adjustments'] ?? $billingCycle->total_adjustments;
                $data['net_amount'] = max(0, $totalAmountCharged - $totalAdjustments);
            }

            // Recalculate days in cycle if period dates are updated
            if (isset($data['period_start']) || isset($data['period_end'])) {
                $start = \Carbon\Carbon::parse($data['period_start'] ?? $billingCycle->period_start);
                $end = \Carbon\Carbon::parse($data['period_end'] ?? $billingCycle->period_end);
                $data['days_in_cycle'] = $start->diffInDays($end);
            }

            $billingCycle->update($data);
            
            DB::commit();
            
            // Reload with relationships
            return $billingCycle->load(['patient', 'visit', 'facility']);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update billing cycle', [
                'billing_cycle_id' => $billingCycle->id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Failed to update billing cycle: ' . $e->getMessage());
        }
    }

    /**
     * Delete a billing cycle
     *
     * @param BillingCycle $billingCycle
     * @return bool
     */
    public function delete(BillingCycle $billingCycle): bool
    {
        try {
            return $billingCycle->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete billing cycle', [
                'billing_cycle_id' => $billingCycle->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Restore a soft deleted billing cycle
     *
     * @param BillingCycle $billingCycle
     * @return bool
     */
    public function restore(BillingCycle $billingCycle): bool
    {
        try {
            return $billingCycle->restore();
        } catch (\Exception $e) {
            Log::error('Failed to restore billing cycle', [
                'billing_cycle_id' => $billingCycle->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Force delete a billing cycle
     *
     * @param BillingCycle $billingCycle
     * @return bool
     */
    public function forceDelete(BillingCycle $billingCycle): bool
    {
        try {
            return $billingCycle->forceDelete();
        } catch (\Exception $e) {
            Log::error('Failed to force delete billing cycle', [
                'billing_cycle_id' => $billingCycle->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update billing status
     *
     * @param BillingCycle $billingCycle
     * @param string $status
     * @param array $additionalData
     * @return BillingCycle
     * @throws \RuntimeException
     */
    public function updateStatus(BillingCycle $billingCycle, string $status, array $additionalData = []): BillingCycle
    {
        DB::beginTransaction();
        
        try {
            $data = ['billing_status' => $status];
            
            // Set billed_at timestamp when moving to certain statuses
            if (in_array($status, ['pending_submission', 'submitted_to_insurance', 'partially_paid'])) {
                $data['billed_at'] = now();
            }
            
            // Set sent_to_collections_at timestamp
            if ($status === 'collections') {
                $data['sent_to_collections_at'] = now();
            }
            
            // Merge additional data
            $data = array_merge($data, $additionalData);
            
            $billingCycle->update($data);
            
            DB::commit();
            
            return $billingCycle->load(['patient', 'visit', 'facility']);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update billing status', [
                'billing_cycle_id' => $billingCycle->id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Failed to update billing status: ' . $e->getMessage());
        }
    }

    /**
     * Record payment received
     *
     * @param BillingCycle $billingCycle
     * @param array $paymentData
     * @return BillingCycle
     * @throws \RuntimeException
     */
    public function recordPayment(BillingCycle $billingCycle, array $paymentData): BillingCycle
    {
        DB::beginTransaction();
        
        try {
            $updateData = [];
            
            // Determine payment type and update appropriate fields
            $paymentType = $paymentData['payment_type'] ?? 'patient';
            $amount = $paymentData['amount'] ?? 0;
            
            if ($paymentType === 'insurance') {
                $updateData['insurance_payment_received'] = 
                    $billingCycle->insurance_payment_received + $amount;
                $updateData['insurance_payment_received_at'] = now();
            } else {
                $updateData['patient_payment_received'] = 
                    $billingCycle->patient_payment_received + $amount;
            }
            
            // Update billing status based on total payments
            $totalPaid = $updateData['insurance_payment_received'] ?? $billingCycle->insurance_payment_received;
            $totalPaid += $updateData['patient_payment_received'] ?? $billingCycle->patient_payment_received;
            
            if ($totalPaid >= $billingCycle->net_amount) {
                $updateData['billing_status'] = 'paid_in_full';
            } elseif ($totalPaid > 0) {
                $updateData['billing_status'] = 'partially_paid';
            }
            
            $billingCycle->update($updateData);
            
            DB::commit();
            
            return $billingCycle->load(['patient', 'visit', 'facility']);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to record payment', [
                'billing_cycle_id' => $billingCycle->id,
                'payment_data' => $paymentData,
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Failed to record payment: ' . $e->getMessage());
        }
    }

    /**
     * Get financial summary by facility
     *
     * @param int $facilityId
     * @param array $dateRange
     * @return array
     */
    public function getFinancialSummary(int $facilityId, array $dateRange = []): array
    {
        try {
            $query = BillingCycle::where('facility_id', $facilityId);
            
            // Apply date range if provided
            if (!empty($dateRange['start'])) {
                $query->where('period_start', '>=', $dateRange['start']);
            }
            if (!empty($dateRange['end'])) {
                $query->where('period_start', '<=', $dateRange['end']);
            }
            
            return [
                'total_cycles' => $query->count(),
                'total_amount_charged' => (float) $query->sum('total_amount_charged'),
                'total_adjustments' => (float) $query->sum('total_adjustments'),
                'net_amount' => (float) $query->sum('net_amount'),
                'total_insurance_payments' => (float) $query->sum('insurance_payment_received'),
                'total_patient_payments' => (float) $query->sum('patient_payment_received'),
                'total_outstanding' => (float) $query->sum(DB::raw('net_amount - insurance_payment_received - patient_payment_received')),
                'by_status' => $query->groupBy('billing_status')
                    ->select('billing_status', DB::raw('COUNT(*) as count'), DB::raw('SUM(net_amount) as total_amount'))
                    ->get()
                    ->toArray(),
                'by_cycle_type' => $query->groupBy('cycle_type')
                    ->select('cycle_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(net_amount) as total_amount'))
                    ->get()
                    ->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get financial summary', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'total_cycles' => 0,
                'total_amount_charged' => 0,
                'total_adjustments' => 0,
                'net_amount' => 0,
                'total_insurance_payments' => 0,
                'total_patient_payments' => 0,
                'total_outstanding' => 0,
                'by_status' => [],
                'by_cycle_type' => [],
            ];
        }
    }
}