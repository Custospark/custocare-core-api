<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class AppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'appointment_uuid' => $this->appointment_uuid,
            'facility' => $this->whenLoaded('facility', function () {
                return [
                    'id' => $this->facility->id,
                    'name' => $this->facility->name,
                    'type' => $this->facility->type,
                ];
            }),
            'patient' => $this->whenLoaded('patient', function () {
                return [
                    'id' => $this->patient->id,
                    'first_name' => $this->patient->first_name,
                    'last_name' => $this->patient->last_name,
                    'date_of_birth' => $this->patient->date_of_birth?->format('Y-m-d'),
                    'medical_record_number' => $this->patient->medical_record_number,
                ];
            }),
            'provider' => $this->whenLoaded('provider', function () {
                return [
                    'id' => $this->provider->id,
                    'first_name' => $this->provider->first_name,
                    'last_name' => $this->provider->last_name,
                    'title' => $this->provider->title,
                    'specialty' => $this->provider->specialty,
                ];
            }),
            'visit' => $this->whenLoaded('visit', function () {
                return [
                    'id' => $this->visit->id,
                    'visit_type' => $this->visit->visit_type,
                    'status' => $this->visit->status,
                ];
            }),
            'department_id' => $this->department_id,
            'appointment_type' => $this->appointment_type,
            'appointment_type_display' => $this->getAppointmentTypeDisplay(),
            'scheduled_start_time' => $this->scheduled_start_time?->format('Y-m-d H:i:s'),
            'scheduled_start_date' => $this->scheduled_start_time?->format('Y-m-d'),
            'scheduled_start_time_display' => $this->scheduled_start_time?->format('M d, Y h:i A'),
            'scheduled_end_time' => $this->scheduled_end_time?->format('Y-m-d H:i:s'),
            'duration_minutes' => $this->duration_minutes,
            'reason_for_visit' => $this->reason_for_visit,
            'requested_services' => $this->requested_services ?? [],
            'status' => $this->status,
            'status_display' => $this->getStatusDisplay(),
            'confirmed_at' => $this->confirmed_at?->format('Y-m-d H:i:s'),
            'checked_in_at' => $this->checked_in_at?->format('Y-m-d H:i:s'),
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at' => $this->cancelled_at?->format('Y-m-d H:i:s'),
            'reminder_sent' => $this->reminder_sent,
            'reminder_sent_at' => $this->reminder_sent_at?->format('Y-m-d H:i:s'),
            'created_visit_id' => $this->created_visit_id,
            'metadata' => $this->metadata ?? [],
            'is_upcoming' => $this->isUpcoming(),
            'is_cancellable' => $this->isCancellable(),
            'is_completed' => $this->isCompleted(),
            'is_in_progress' => $this->isInProgress(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get display name for appointment type
     */
    private function getAppointmentTypeDisplay(): string
    {
        $displayNames = [
            'new_patient_consultation' => 'New Patient Consultation',
            'followup_visit' => 'Follow-up Visit',
            'annual_physical' => 'Annual Physical',
            'procedure' => 'Procedure',
            'diagnostic_test' => 'Diagnostic Test',
            'therapy_session' => 'Therapy Session',
            'telehealth' => 'Telehealth',
            'vaccination' => 'Vaccination',
            'consultation' => 'Consultation',
        ];

        return $displayNames[$this->appointment_type] ?? ucfirst(str_replace('_', ' ', $this->appointment_type));
    }

    /**
     * Get display name for status
     */
    private function getStatusDisplay(): string
    {
        $displayNames = [
            'scheduled' => 'Scheduled',
            'confirmed' => 'Confirmed',
            'checked_in' => 'Checked In',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'no_show' => 'No Show',
            'cancelled' => 'Cancelled',
            'rescheduled' => 'Rescheduled',
        ];

        return $displayNames[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Customize the outgoing response for the resource.
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
            'message' => $this->getResponseMessage($request),
            'meta' => [
                'api_version' => '1.0',
                'timestamp' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Get appropriate response message based on request method
     */
    private function getResponseMessage(Request $request): string
    {
        $method = $request->method();
        
        return match($method) {
            'GET' => 'Appointment retrieved successfully',
            'POST' => 'Appointment created successfully',
            'PUT', 'PATCH' => 'Appointment updated successfully',
            'DELETE' => 'Appointment deleted successfully',
            default => 'Request completed successfully',
        };
    }
}