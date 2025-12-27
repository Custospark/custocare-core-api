<?php

namespace App\Repositories\PatientConsent;

use App\Models\PatientConsent;
use App\Repositories\Contracts\PatientConsentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class PatientConsentRepository implements PatientConsentRepositoryInterface
{
    /**
     * Find a consent by its UUID.
     *
     * @param string $uuid
     * @return PatientConsent|null
     */
    public function findByUuid(string $uuid): ?PatientConsent
    {
        try {
            return PatientConsent::where('consent_uuid', $uuid)->first();
        } catch (\Exception $e) {
            Log::error('Error finding consent by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find active consent by patient and type.
     *
     * @param int $patientId
     * @param string $consentType
     * @return PatientConsent|null
     */
    public function findActiveConsent(int $patientId, string $consentType): ?PatientConsent
    {
        try {
            return PatientConsent::where('patient_id', $patientId)
                ->where('consent_type', $consentType)
                ->active()
                ->latest('granted_at')
                ->first();
        } catch (\Exception $e) {
            Log::error('Error finding active consent', [
                'patient_id' => $patientId,
                'consent_type' => $consentType,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all consents for a patient.
     *
     * @param int $patientId
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getByPatient(int $patientId, array $filters = []): LengthAwarePaginator
    {
        try {
            $query = PatientConsent::where('patient_id', $patientId)
                ->with(['patient', 'witness', 'revoker']);

            // Apply filters
            if (isset($filters['consent_type'])) {
                $query->where('consent_type', $filters['consent_type']);
            }

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['from_date'])) {
                $query->whereDate('granted_at', '>=', $filters['from_date']);
            }

            if (isset($filters['to_date'])) {
                $query->whereDate('granted_at', '<=', $filters['to_date']);
            }

            if (isset($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('consent_uuid', 'like', "%{$search}%")
                        ->orWhere('scope_limitations', 'like', "%{$search}%")
                        ->orWhere('revocation_reason', 'like', "%{$search}%");
                });
            }

            // Default ordering
            $orderBy = $filters['order_by'] ?? 'granted_at';
            $orderDirection = $filters['order_direction'] ?? 'desc';

            return $query->orderBy($orderBy, $orderDirection)
                ->paginate($filters['per_page'] ?? 20);
        } catch (\Exception $e) {
            Log::error('Error getting patient consents', [
                'patient_id' => $patientId,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            // Return empty paginator instead of throwing
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        }
    }

    /**
     * Get expiring consents.
     *
     * @param int $daysThreshold
     * @return Collection
     */
    public function getExpiringConsents(int $daysThreshold = 30): Collection
    {
        try {
            $expiryDate = now()->addDays($daysThreshold);

            return PatientConsent::where('status', 'active')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $expiryDate)
                ->where('expires_at', '>', now())
                ->with('patient')
                ->get();
        } catch (\Exception $e) {
            Log::error('Error getting expiring consents', [
                'days_threshold' => $daysThreshold,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * Get revoked consents.
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getRevokedConsents(array $filters = []): LengthAwarePaginator
    {
        try {
            $query = PatientConsent::where('status', 'revoked')
                ->orWhereNotNull('revoked_at')
                ->with(['patient', 'revoker']);

            if (isset($filters['from_date'])) {
                $query->whereDate('revoked_at', '>=', $filters['from_date']);
            }

            if (isset($filters['to_date'])) {
                $query->whereDate('revoked_at', '<=', $filters['to_date']);
            }

            if (isset($filters['patient_id'])) {
                $query->where('patient_id', $filters['patient_id']);
            }

            return $query->orderBy('revoked_at', 'desc')
                ->paginate($filters['per_page'] ?? 20);
        } catch (\Exception $e) {
            Log::error('Error getting revoked consents', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        }
    }

    /**
     * Create a new consent.
     *
     * @param array $data
     * @return PatientConsent
     */
    public function create(array $data): PatientConsent
    {
        DB::beginTransaction();
        
        try {
            $consent = PatientConsent::create($data);
            
            // Add to audit trail
            $auditTrail = [
                'created_at' => now()->toISOString(),
                'created_by' => auth::id()?? 'system',
                'action' => 'consent_created',
                'data_snapshot' => $consent->toArray()
            ];
            
            $consent->audit_trail = [$auditTrail];
            $consent->save();
            
            DB::commit();
            return $consent;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating consent', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update a consent.
     *
     * @param PatientConsent $consent
     * @param array $data
     * @return bool
     */
    public function update(PatientConsent $consent, array $data): bool
    {
        DB::beginTransaction();
        
        try {
            $oldData = $consent->toArray();
            $updated = $consent->update($data);
            
            if ($updated) {
                // Add to audit trail
                $auditTrail = [
                    'updated_at' => now()->toISOString(),
                    'updated_by' => auth::id() ?? 'system',
                    'action' => 'consent_updated',
                    'old_data' => $oldData,
                    'new_data' => $consent->fresh()->toArray()
                ];
                
                $currentAudit = $consent->audit_trail ?? [];
                $currentAudit[] = $auditTrail;
                
                $consent->audit_trail = $currentAudit;
                $consent->save();
            }
            
            DB::commit();
            return $updated;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating consent', [
                'consent_id' => $consent->id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Revoke a consent.
     *
     * @param PatientConsent $consent
     * @param array $revocationData
     * @return bool
     */
    public function revoke(PatientConsent $consent, array $revocationData): bool
    {
        DB::beginTransaction();
        
        try {
            $revocationData['status'] = 'revoked';
            $revocationData['revoked_at'] = now();
            
            $updated = $this->update($consent, $revocationData);
            
            if ($updated) {
                // Add revocation-specific audit entry
                $auditTrail = [
                    'revoked_at' => now()->toISOString(),
                    'revoked_by' => auth::id() ?? 'system',
                    'action' => 'consent_revoked',
                    'reason' => $revocationData['revocation_reason'] ?? null
                ];
                
                $currentAudit = $consent->audit_trail ?? [];
                $currentAudit[] = $auditTrail;
                
                $consent->audit_trail = $currentAudit;
                $consent->save();
            }
            
            DB::commit();
            return $updated;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error revoking consent', [
                'consent_id' => $consent->id,
                'revocation_data' => $revocationData,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Supersede a consent with a new one.
     *
     * @param PatientConsent $consent
     * @param array $newConsentData
     * @return PatientConsent
     */
    public function supersede(PatientConsent $consent, array $newConsentData): PatientConsent
    {
        DB::beginTransaction();
        
        try {
            // Update old consent
            $consent->update([
                'status' => 'superseded',
                'superseded_by_consent_id' => null // Will be set after new consent creation
            ]);
            
            // Create new consent
            $newConsentData['patient_id'] = $consent->patient_id;
            $newConsent = $this->create($newConsentData);
            
            // Link old consent to new one
            $consent->superseded_by_consent_id = $newConsent->id;
            $consent->save();
            
            DB::commit();
            return $newConsent;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error superseding consent', [
                'old_consent_id' => $consent->id,
                'new_consent_data' => $newConsentData,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Check if consent is valid for specific scope.
     *
     * @param PatientConsent $consent
     * @param array $scopeCheck
     * @return bool
     */
    public function validateScope(PatientConsent $consent, array $scopeCheck): bool
    {
        try {
            // Check if consent is active
            if (!$consent->isActive()) {
                return false;
            }

            // Check facility scope
            if (isset($scopeCheck['facility_id']) && $consent->scope_facility_ids) {
                if (!in_array($scopeCheck['facility_id'], (array) $consent->scope_facility_ids)) {
                    return false;
                }
            }

            // Check department scope
            if (isset($scopeCheck['department_id']) && $consent->scope_department_ids) {
                if (!in_array($scopeCheck['department_id'], (array) $consent->scope_department_ids)) {
                    return false;
                }
            }

            // Check staff scope
            if (isset($scopeCheck['staff_id']) && $consent->scope_staff_ids) {
                if (!in_array($scopeCheck['staff_id'], (array) $consent->scope_staff_ids)) {
                    return false;
                }
            }

            // Check service category scope
            if (isset($scopeCheck['service_category']) && $consent->scope_service_categories) {
                if (!in_array($scopeCheck['service_category'], (array) $consent->scope_service_categories)) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error validating consent scope', [
                'consent_id' => $consent->id,
                'scope_check' => $scopeCheck,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get consent statistics.
     *
     * @param array $filters
     * @return array
     */
    public function getStatistics(array $filters = []): array
    {
        try {
            $query = PatientConsent::query();
            
            if (isset($filters['patient_id'])) {
                $query->where('patient_id', $filters['patient_id']);
            }
            
            if (isset($filters['from_date'])) {
                $query->whereDate('granted_at', '>=', $filters['from_date']);
            }
            
            if (isset($filters['to_date'])) {
                $query->whereDate('granted_at', '<=', $filters['to_date']);
            }

            $total = $query->count();
            $active = $query->clone()->where('status', 'active')->count();
            $expired = $query->clone()->where('status', 'expired')->count();
            $revoked = $query->clone()->where('status', 'revoked')->count();
            
            // Count by consent type
            $byType = $query->clone()
                ->select('consent_type', DB::raw('count(*) as count'))
                ->groupBy('consent_type')
                ->pluck('count', 'consent_type')
                ->toArray();

            // Count by legal basis
            $byLegalBasis = $query->clone()
                ->select('legal_basis', DB::raw('count(*) as count'))
                ->groupBy('legal_basis')
                ->pluck('count', 'legal_basis')
                ->toArray();

            return [
                'total' => $total,
                'active' => $active,
                'expired' => $expired,
                'revoked' => $revoked,
                'by_type' => $byType,
                'by_legal_basis' => $byLegalBasis,
                'active_percentage' => $total > 0 ? round(($active / $total) * 100, 2) : 0,
            ];
        } catch (\Exception $e) {
            Log::error('Error getting consent statistics', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            return [
                'total' => 0,
                'active' => 0,
                'expired' => 0,
                'revoked' => 0,
                'by_type' => [],
                'by_legal_basis' => [],
                'active_percentage' => 0,
            ];
        }
    }
}