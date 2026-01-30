<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $showSensitiveData = $user && $user->hasAnyRole(['compliance_officer', 'system_admin', 'super_admin']);

        return [
            'id' => $this->id,
            'audit_uuid' => $this->audit_uuid,
            'timestamp' => $this->created_at->toISOString(),
            
            // Operation details
            'operation' => $this->operation,
            'result' => $this->result,
            'failure_reason' => $this->when($this->result !== 'success', $this->failure_reason),
            'error_code' => $this->error_code,
            'operation_duration_ms' => $this->operation_duration_ms,
            'operation_duration_seconds' => $this->when($this->operation_duration_ms, 
                round($this->operation_duration_ms / 1000, 2)),
            
            // Entity information
            'entity' => [
                'type' => $this->entity_type,
                'id' => $this->entity_id,
                'identifier' => $this->entity_identifier,
                'name' => $this->entityName,
            ],
            
            // Change tracking (only show to authorized users)
            'changes' => $this->when($showSensitiveData, [
                'previous_values' => $this->previous_values,
                'new_values' => $this->new_values,
                'changed_fields' => $this->changed_fields,
            ]),
            
            // Actor information
            'performed_by' => [
                'type' => $this->performed_by_type,
                'id' => $this->performed_by_id,
                'identifier' => $this->performed_by_identifier,
                'role' => $this->performed_by_role,
                'name' => $this->performerName,
            ],
            
            // Request context
            'request_context' => [
                'request_id' => $this->request_id,
                'session_id' => $this->session_id,
                'user_ip' => $this->user_ip,
                'user_agent' => $this->when($showSensitiveData, $this->user_agent),
                'geolocation' => $this->geolocation,
            ],
            
            // Compliance information
            'compliance' => [
                'reason' => $this->compliance_reason,
                'legal_hold' => $this->legal_hold_flag,
                'justification' => $this->when($this->compliance_reason === 'break_glass', 
                    $this->justification),
            ],
            
            // Facility context
            'facility_context' => $this->when($this->facility_id || $this->department_id, [
                'facility_id' => $this->facility_id,
                'department_id' => $this->department_id,
            ]),
            
            // Patient privacy information
            'patient_privacy' => $this->when($this->patient_id, [
                'patient_id' => $this->patient_id,
                'phi_accessed' => $this->phi_accessed,
                'phi_fields_accessed' => $this->when($showSensitiveData && $this->phi_accessed, 
                    $this->phi_fields_accessed),
            ]),
            
            // Metadata
            'metadata' => $this->when($showSensitiveData, $this->metadata),
            
            // System information
            'system' => [
                'created_at' => $this->created_at->toISOString(),
                'archived_at' => $this->archived_at?->toISOString(),
                'purged_at' => $this->purged_at?->toISOString(),
                'age_days' => $this->created_at->diffInDays(now()),
                'retention_status' => $this->getRetentionStatus(),
            ],
            
            // Links
            'links' => [
                'self' => route('audit-logs.show', $this->id),
                'entity' => $this->when($this->entity_type && $this->entity_id, 
                    $this->getEntityUrl()),
                'performer' => $this->when($this->performed_by_type && $this->performed_by_id, 
                    $this->getPerformerUrl()),
                'patient' => $this->when($this->patient_id, 
                    route('patients.show', $this->patient_id)),
            ],
        ];
    }

    /**
     * Get the retention status of the audit log.
     *
     * @return string
     */
    private function getRetentionStatus(): string
    {
        if ($this->purged_at) {
            return 'purged';
        }
        
        if ($this->archived_at) {
            return 'archived';
        }
        
        if ($this->legal_hold_flag) {
            return 'legal_hold';
        }
        
        $age = $this->created_at->diffInDays(now());
        
        if ($age > 2555) { // 7 years
            return 'eligible_for_purging';
        }
        
        if ($age > 90) { // 90 days
            return 'eligible_for_archival';
        }
        
        return 'active';
    }

    /**
     * Get the entity URL based on entity type.
     *
     * @return string|null
     */
    private function getEntityUrl(): ?string
    {
        if (!$this->entity_type || !$this->entity_id) {
            return null;
        }

        // Map entity types to their respective routes
        $routes = [
            'patient' => 'patients.show',
            'staff' => 'staff.show',
            'appointment' => 'appointments.show',
            'medical_record' => 'medical-records.show',
            'prescription' => 'prescriptions.show',
            'lab_result' => 'lab-results.show',
            'billing' => 'billings.show',
        ];

        $routeName = $routes[strtolower($this->entity_type)] ?? null;
        
        return $routeName ? route($routeName, $this->entity_id) : null;
    }

    /**
     * Get the performer URL based on performer type.
     *
     * @return string|null
     */
    private function getPerformerUrl(): ?string
    {
        if (!$this->performed_by_type || !$this->performed_by_id) {
            return null;
        }

        // Map performer types to their respective routes
        $routes = [
            'staff' => 'staff.show',
            'patient' => 'patients.show',
            'system' => null, // System doesn't have a profile page
            'external_api' => null,
            'scheduled_job' => null,
        ];

        $routeName = $routes[strtolower($this->performed_by_type)] ?? null;
        
        return $routeName ? route($routeName, $this->performed_by_id) : null;
    }

    /**
     * Customize the response for the resource.
     *
     * @param Request $request
     * @param \Illuminate\Http\JsonResponse $response
     * @return void
     */
    public function withResponse($request, $response): void
    {
        $response->header('X-Audit-Log-Immutable', 'true');
        $response->header('X-Retention-Period', '7 years');
        
        // Add cache control headers
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        
        // Add security headers
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'DENY');
    }

    /**
     * Add additional metadata to the resource response.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'api_version' => config('app.api_version', 'v1'),
                'copyright' => '© ' . date('Y') . ' Healthcare System',
                'terms_of_service' => url('/terms'),
                'privacy_policy' => url('/privacy'),
                'compliance' => [
                    'hipaa_compliant' => true,
                    'gdpr_compliant' => true,
                    'retention_period_years' => 7,
                ],
            ],
            'links' => [
                'self' => $request->fullUrl(),
                'documentation' => url('/api/docs/audit-logs'),
                'related' => [
                    'statistics' => route('audit-logs.statistics'),
                    'exports' => route('audit-logs.export'),
                ],
            ],
        ];
    }
}