<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientVisitSummaryViewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'active_visit_ids' => $this->active_visit_ids,
            'active_visits_count' => $this->active_visits_count,
            'recent_visits_last_30_days' => $this->recent_visits_last_30_days,
            'visits_last_30_days_count' => $this->visits_last_30_days_count,
            'last_visit_date' => $this->last_visit_date,
            'last_visit_facility' => new FacilityResource($this->whenLoaded('lastVisitFacility')),
            'last_visit_facility_id' => $this->last_visit_facility_id,
            'upcoming_appointments' => $this->upcoming_appointments,
            'next_appointment_at' => $this->next_appointment_at,
            'active_prescriptions' => $this->active_prescriptions,
            'pending_prescriptions' => $this->pending_prescriptions,
            'active_prescriptions_count' => $this->active_prescriptions_count,
            'outstanding_bills_total' => $this->outstanding_bills_total,
            'unpaid_invoices_count' => $this->unpaid_invoices_count,
            'payment_plans' => $this->payment_plans,
            'health_metrics_trends' => $this->health_metrics_trends,
            'recent_lab_results' => $this->recent_lab_results,
            'recent_imaging_results' => $this->recent_imaging_results,
            'care_team_members' => $this->care_team_members,
            'primary_care_provider' => new UserResource($this->whenLoaded('primaryCareProvider')),
            'primary_care_provider_id' => $this->primary_care_provider_id,
            'preventive_care_due' => $this->preventive_care_due,
            'immunizations_due' => $this->immunizations_due,
            'screenings_due' => $this->screenings_due,
            'patient_alerts' => $this->patient_alerts,
            'unread_messages_count' => $this->unread_messages_count,
            'last_updated_at' => $this->last_updated_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Computed fields for easier consumption
            'has_upcoming_appointments' => $this->next_appointment_at && $this->next_appointment_at > now(),
            'has_outstanding_bills' => $this->outstanding_bills_total > 0,
            'needs_attention' => $this->has_upcoming_appointments || $this->has_outstanding_bills || 
                               !empty($this->patient_alerts) || !empty($this->preventive_care_due),
            'summary_status' => $this->getSummaryStatus(),
        ];
    }

    /**
     * Get summary status based on various factors.
     *
     * @return string
     */
    private function getSummaryStatus(): string
    {
        if ($this->active_visits_count > 0) {
            return 'active_care';
        }
        
        if ($this->next_appointment_at && $this->next_appointment_at > now()) {
            return 'upcoming_appointment';
        }
        
        if ($this->outstanding_bills_total > 0) {
            return 'financial_attention_needed';
        }
        
        if (!empty($this->preventive_care_due) || !empty($this->immunizations_due) || !empty($this->screenings_due)) {
            return 'preventive_care_due';
        }
        
        return 'stable';
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Patient visit summary retrieved successfully',
            'meta' => [
                'version' => '1.0',
                'copyright' => 'Healthcare System API',
                'timestamp' => now()->toIso8601String(),
            ],
        ];
    }
}