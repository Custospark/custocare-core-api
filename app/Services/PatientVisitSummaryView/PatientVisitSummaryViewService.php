<?php

namespace App\Services\PatientVisitSummaryView;

use App\Models\Patient;
use App\Models\PatientVisitSummaryView;
use App\Repositories\Contracts\PatientVisitSummaryViewRepositoryInterface;
use App\Services\Contracts\PatientVisitSummaryViewServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientVisitSummaryViewService implements PatientVisitSummaryViewServiceInterface
{
    /**
     * PatientVisitSummaryView repository instance.
     *
     * @var PatientVisitSummaryViewRepositoryInterface
     */
    protected PatientVisitSummaryViewRepositoryInterface $repository;

    /**
     * Constructor.
     *
     * @param PatientVisitSummaryViewRepositoryInterface $repository
     */
    public function __construct(PatientVisitSummaryViewRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * {@inheritDoc}
     */
    public function getSummaryViewById(int $id): array
    {
        try {
            $summary = $this->repository->findById($id);
            
            if (!$summary) {
                return [
                    'success' => false,
                    'message' => 'Patient visit summary not found',
                    'data' => null,
                    'status' => 404,
                ];
            }

            return [
                'success' => true,
                'message' => 'Patient visit summary retrieved successfully',
                'data' => $summary,
                'status' => 200,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get summary view by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve patient visit summary',
                'data' => null,
                'status' => 500,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getSummaryByPatientId(int $patientId): array
    {
        try {
            $summary = $this->repository->findByPatientId($patientId);
            
            if (!$summary) {
                // Optionally create summary if not found
                return $this->refreshSummaryView($patientId);
            }

            // Check if summary needs refresh (older than 24 hours)
            if ($summary->last_updated_at->diffInHours(now()) > 24) {
                return $this->refreshSummaryView($patientId);
            }

            return [
                'success' => true,
                'message' => 'Patient visit summary retrieved successfully',
                'data' => $summary,
                'status' => 200,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get summary by patient ID', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve patient visit summary',
                'data' => null,
                'status' => 500,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getAllSummaries(array $filters = [], int $perPage = 20): array
    {
        try {
            $summaries = $this->repository->paginate($filters, $perPage);

            return [
                'success' => true,
                'message' => 'Patient visit summaries retrieved successfully',
                'data' => $summaries,
                'status' => 200,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get all summaries', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve patient visit summaries',
                'data' => null,
                'status' => 500,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function createSummaryView(array $data): array
    {
        try {
            // Validate required fields
            if (!isset($data['patient_id'])) {
                return [
                    'success' => false,
                    'message' => 'Patient ID is required',
                    'data' => null,
                    'status' => 422,
                ];
            }

            // Check if summary already exists for patient
            $existing = $this->repository->findByPatientId($data['patient_id']);
            if ($existing) {
                return [
                    'success' => false,
                    'message' => 'Summary view already exists for this patient',
                    'data' => $existing,
                    'status' => 409,
                ];
            }

            $summary = $this->repository->create($data);

            return [
                'success' => true,
                'message' => 'Patient visit summary created successfully',
                'data' => $summary,
                'status' => 201,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create summary view', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create patient visit summary',
                'data' => null,
                'status' => 500,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateSummaryView(int $id, array $data): array
    {
        try {
            $summary = $this->repository->update($id, $data);

            return [
                'success' => true,
                'message' => 'Patient visit summary updated successfully',
                'data' => $summary,
                'status' => 200,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update summary view', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update patient visit summary',
                'data' => null,
                'status' => 500,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function refreshSummaryView(int $patientId): array
    {
        try {
            DB::beginTransaction();

            // In a real implementation, this would fetch data from various sources
            // For now, we'll simulate the data collection
            $patient = Patient::with([
                'visits' => function ($query) {
                    $query->where('status', 'active')->latest();
                },
                'appointments' => function ($query) {
                    $query->where('status', 'scheduled')->where('date', '>', now());
                },
                'prescriptions' => function ($query) {
                    $query->whereIn('status', ['active', 'pending']);
                },
                'invoices' => function ($query) {
                    $query->where('status', 'unpaid');
                },
            ])->find($patientId);

            if (!$patient) {
                return [
                    'success' => false,
                    'message' => 'Patient not found',
                    'data' => null,
                    'status' => 404,
                ];
            }

            // Build summary data
            $summaryData = $this->buildSummaryData($patient);

            // Update or create summary
            $summary = $this->repository->updateOrCreateByPatientId($patientId, $summaryData);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Patient visit summary refreshed successfully',
                'data' => $summary,
                'status' => 200,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to refresh summary view', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to refresh patient visit summary',
                'data' => null,
                'status' => 500,
            ];
        }
    }

    /**
     * Build summary data from patient information.
     *
     * @param Patient $patient
     * @return array
     */
    private function buildSummaryData(Patient $patient): array
    {
        // This is a simplified version. In production, this would be more complex
        $activeVisits = $patient->visits->where('status', 'active');
        $upcomingAppointments = $patient->appointments->where('date', '>', now());
        $nextAppointment = $upcomingAppointments->sortBy('date')->first();

        return [
            'active_visit_ids' => $activeVisits->pluck('id')->toArray(),
            'active_visits_count' => $activeVisits->count(),
            'recent_visits_last_30_days' => $patient->visits
                ->where('visit_date', '>=', now()->subDays(30))
                ->take(10)
                ->values()
                ->toArray(),
            'visits_last_30_days_count' => $patient->visits
                ->where('visit_date', '>=', now()->subDays(30))
                ->count(),
            'last_visit_date' => $patient->visits->max('visit_date'),
            'last_visit_facility_id' => $patient->visits->last()?->facility_id,
            'upcoming_appointments' => $upcomingAppointments->take(5)->values()->toArray(),
            'next_appointment_at' => $nextAppointment?->date,
            'active_prescriptions' => $patient->prescriptions
                ->where('status', 'active')
                ->take(10)
                ->values()
                ->toArray(),
            'pending_prescriptions' => $patient->prescriptions
                ->where('status', 'pending')
                ->take(5)
                ->values()
                ->toArray(),
            'active_prescriptions_count' => $patient->prescriptions->where('status', 'active')->count(),
            'outstanding_bills_total' => $patient->invoices->sum('amount'),
            'unpaid_invoices_count' => $patient->invoices->count(),
            'payment_plans' => [], // Would fetch from payment service
            'health_metrics_trends' => $this->getHealthMetricsForPatient($patient->id),
            'recent_lab_results' => [], // Would fetch from lab service
            'recent_imaging_results' => [], // Would fetch from imaging service
            'care_team_members' => $this->getCareTeamForPatient($patient->id),
            'primary_care_provider_id' => $patient->primary_care_provider_id,
            'preventive_care_due' => $this->getPreventiveCareDue($patient->id),
            'immunizations_due' => $this->getImmunizationsDue($patient->id),
            'screenings_due' => $this->getScreeningsDue($patient->id),
            'patient_alerts' => $this->getPatientAlerts($patient->id),
            'unread_messages_count' => $this->getUnreadMessagesCount($patient->id),
        ];
    }

    /**
     * Get health metrics for a patient.
     *
     * @param int $patientId
     * @return array
     */
    private function getHealthMetricsForPatient(int $patientId): array
    {
        // Stub implementation - would query health metrics service
        return [];
    }

    /**
     * Get care team for a patient.
     *
     * @param int $patientId
     * @return array
     */
    private function getCareTeamForPatient(int $patientId): array
    {
        // Stub implementation - would query care coordination service
        return [];
    }

    /**
     * Get preventive care due for a patient.
     *
     * @param int $patientId
     * @return array
     */
    private function getPreventiveCareDue(int $patientId): array
    {
        // Stub implementation - would query preventive care service
        return [];
    }

    /**
     * Get immunizations due for a patient.
     *
     * @param int $patientId
     * @return array
     */
    private function getImmunizationsDue(int $patientId): array
    {
        // Stub implementation - would query immunization service
        return [];
    }

    /**
     * Get screenings due for a patient.
     *
     * @param int $patientId
     * @return array
     */
    private function getScreeningsDue(int $patientId): array
    {
        // Stub implementation - would query screening service
        return [];
    }

    /**
     * Get patient alerts.
     *
     * @param int $patientId
     * @return array
     */
    private function getPatientAlerts(int $patientId): array
    {
        // Stub implementation - would query alerts service
        return [];
    }

    /**
     * Get unread messages count.
     *
     * @param int $patientId
     * @return int
     */
    private function getUnreadMessagesCount(int $patientId): int
    {
        // Stub implementation - would query messaging service
        return 0;
    }

    /**
     * {@inheritDoc}
     */
    public function batchRefreshSummaryViews(array $patientIds): array
    {
        try {
            $results = [];
            $successCount = 0;
            $errorCount = 0;

            foreach ($patientIds as $patientId) {
                try {
                    $result = $this->refreshSummaryView($patientId);
                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                    $results[] = [
                        'patient_id' => $patientId,
                        'success' => $result['success'],
                        'message' => $result['message'],
                    ];
                } catch (\Exception $e) {
                    $errorCount++;
                    $results[] = [
                        'patient_id' => $patientId,
                        'success' => false,
                        'message' => 'Failed to refresh summary',
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Failed to refresh summary in batch', [
                        'patient_id' => $patientId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return [
                'success' => $errorCount === 0,
                'message' => "Batch refresh completed. Success: {$successCount}, Failed: {$errorCount}",
                'data' => [
                    'total' => count($patientIds),
                    'success' => $successCount,
                    'failed' => $errorCount,
                    'results' => $results,
                ],
                'status' => $errorCount === 0 ? 200 : 207, // 207 Multi-Status
            ];
        } catch (\Exception $e) {
            Log::error('Failed to batch refresh summary views', [
                'patient_ids' => $patientIds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to batch refresh patient visit summaries',
                'data' => null,
                'status' => 500,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deleteSummaryView(int $id): array
    {
        try {
            $deleted = $this->repository->delete($id);

            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete patient visit summary',
                    'data' => null,
                    'status' => 500,
                ];
            }

            return [
                'success' => true,
                'message' => 'Patient visit summary deleted successfully',
                'data' => null,
                'status' => 200,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete summary view', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete patient visit summary',
                'data' => null,
                'status' => 500,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getUpcomingAppointments(string $startDate, string $endDate): array
    {
        try {
            $summaries = $this->repository->getWithUpcomingAppointments($startDate, $endDate);

            return [
                'success' => true,
                'message' => 'Upcoming appointments retrieved successfully',
                'data' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'appointments' => $summaries,
                ],
                'status' => 200,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get upcoming appointments', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve upcoming appointments',
                'data' => null,
                'status' => 500,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getHealthMetricsTrends(int $patientId): array
    {
        try {
            $summary = $this->repository->findByPatientId($patientId);
            
            if (!$summary) {
                return [
                    'success' => false,
                    'message' => 'Patient visit summary not found',
                    'data' => null,
                    'status' => 404,
                ];
            }

            $healthMetrics = $summary->health_metrics_trends ?? [];

            return [
                'success' => true,
                'message' => 'Health metrics trends retrieved successfully',
                'data' => [
                    'patient_id' => $patientId,
                    'metrics' => $healthMetrics,
                    'last_updated' => $summary->last_updated_at,
                ],
                'status' => 200,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get health metrics trends', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve health metrics trends',
                'data' => null,
                'status' => 500,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
  /**
 * Get care coordination insights
 */
public function getCareCoordinationInsights(array $filters = []): array
{
    try {
        $paginator = $this->repository->paginate($filters, 50);

        // ✅ SAFE across all Laravel versions & custom repos
        $summaries = collect($paginator->items());

        $insights = [
            'total_patients' => $paginator->total(),
            'patients_with_upcoming_appointments' => 0,
            'patients_with_outstanding_bills' => 0,
            'patients_with_preventive_care_due' => 0,
            'average_visits_last_30_days' => 0,
            'total_outstanding_bills' => 0,
        ];

        $totalVisits = 0;
        $totalBills = 0;

        foreach ($summaries as $summary) {
            if (!empty($summary->next_appointment_at) && $summary->next_appointment_at->isFuture()) {
                $insights['patients_with_upcoming_appointments']++;
            }

            if ($summary->outstanding_bills_total > 0) {
                $insights['patients_with_outstanding_bills']++;
                $totalBills += $summary->outstanding_bills_total;
            }

            if (!empty($summary->preventive_care_due)) {
                $insights['patients_with_preventive_care_due']++;
            }

            $totalVisits += (int) $summary->visits_last_30_days_count;
        }

        $count = $summaries->count();

        if ($count > 0) {
            $insights['average_visits_last_30_days'] = round($totalVisits / $count, 2);
            $insights['total_outstanding_bills'] = $totalBills;
        }

        return [
            'success' => true,
            'message' => 'Care coordination insights retrieved successfully',
            'data' => [
                'insights' => $insights,
                'filters_applied' => $filters,
            ],
            'status' => 200,
        ];
    } catch (\Throwable $e) {
        Log::error('Failed to get care coordination insights', [
            'filters' => $filters,
            'error' => $e->getMessage(),
        ]);

        return [
            'success' => false,
            'message' => 'Failed to retrieve care coordination insights',
            'data' => null,
            'status' => 500,
        ];
    }
}


}