<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="PatientVisitSummaryView",
 *     type="object",
 *     description="Patient portal & care coordination summary view"
 * )
 */
class PatientVisitSummaryView extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'patient_id',
        'active_visit_ids',
        'active_visits_count',
        'recent_visits_last_30_days',
        'visits_last_30_days_count',
        'last_visit_date',
        'last_visit_facility_id',
        'upcoming_appointments',
        'next_appointment_at',
        'active_prescriptions',
        'pending_prescriptions',
        'active_prescriptions_count',
        'outstanding_bills_total',
        'unpaid_invoices_count',
        'payment_plans',
        'health_metrics_trends',
        'recent_lab_results',
        'recent_imaging_results',
        'care_team_members',
        'primary_care_provider_id',
        'preventive_care_due',
        'immunizations_due',
        'screenings_due',
        'patient_alerts',
        'unread_messages_count',
        'last_updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'active_visit_ids' => 'array',
        'recent_visits_last_30_days' => 'array',
        'upcoming_appointments' => 'array',
        'active_prescriptions' => 'array',
        'pending_prescriptions' => 'array',
        'payment_plans' => 'array',
        'health_metrics_trends' => 'array',
        'recent_lab_results' => 'array',
        'recent_imaging_results' => 'array',
        'care_team_members' => 'array',
        'preventive_care_due' => 'array',
        'immunizations_due' => 'array',
        'screenings_due' => 'array',
        'patient_alerts' => 'array',
        'last_visit_date' => 'datetime',
        'next_appointment_at' => 'datetime',
        'last_updated_at' => 'datetime',
        'outstanding_bills_total' => 'decimal:2',
    ];

    /**
     * Get the patient associated with the summary view.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the last visit facility.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function lastVisitFacility()
    {
        return $this->belongsTo(Facility::class, 'last_visit_facility_id');
    }

    /**
     * Get the primary care provider.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function primaryCareProvider()
    {
        return $this->belongsTo(User::class, 'primary_care_provider_id');
    }

    /**
     * Scope a query to only include summaries for active patients.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->whereHas('patient', function ($q) {
            $q->where('status', 'active');
        });
    }

    /**
     * Scope a query to only include summaries with upcoming appointments.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithUpcomingAppointments($query)
    {
        return $query->whereNotNull('next_appointment_at')
            ->where('next_appointment_at', '>', now());
    }

    /**
     * Scope a query to only include summaries with outstanding bills.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithOutstandingBills($query)
    {
        return $query->where('outstanding_bills_total', '>', 0);
    }
}