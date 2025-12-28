<?php

namespace App\Services\Appointment;

use App\Models\Appointment;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Services\Contracts\AppointmentServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AppointmentService implements AppointmentServiceInterface
{
    /**
     * Appointment repository instance
     */
    private AppointmentRepositoryInterface $appointmentRepository;

    /**
     * Constructor
     */
    public function __construct(AppointmentRepositoryInterface $appointmentRepository)
    {
        $this->appointmentRepository = $appointmentRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllAppointments(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            return $this->appointmentRepository->all($filters, $perPage);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get all appointments', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            // Return empty paginator on error
            return new \Illuminate\Pagination\LengthAwarePaginator(
                [],
                0,
                $perPage,
                1
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAppointmentByUuid(string $uuid): ?Appointment
    {
        try {
            $appointment = $this->appointmentRepository->findByUuid($uuid);
            
            if (!$appointment) {
                Log::warning('Service: Appointment not found', ['uuid' => $uuid]);
                return null;
            }

            return $appointment;
        } catch (\Exception $e) {
            Log::error('Service: Failed to get appointment by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createAppointment(array $data): array
    {
        try {
            // Validate appointment data
            $validationResult = $this->validateAppointmentData($data);
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validationResult['errors'],
                    'data' => null
                ];
            }

            // Check for scheduling conflicts
            $conflict = $this->appointmentRepository->hasSchedulingConflict(
                $data['facility_id'],
                $data['provider_staff_id'],
                Carbon::parse($data['scheduled_start_time']),
                Carbon::parse($data['scheduled_start_time'])->addMinutes($data['duration_minutes'])
            );

            if ($conflict) {
                return [
                    'success' => false,
                    'message' => 'Scheduling conflict detected',
                    'errors' => ['scheduled_start_time' => ['This time slot is already booked']],
                    'data' => null
                ];
            }

            // Create appointment
            $appointment = $this->appointmentRepository->create($data);
            
            if (!$appointment) {
                return [
                    'success' => false,
                    'message' => 'Failed to create appointment',
                    'errors' => [],
                    'data' => null
                ];
            }

            Log::info('Service: Appointment created successfully', [
                'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient_id,
                'provider_id' => $appointment->provider_staff_id
            ]);

            return [
                'success' => true,
                'message' => 'Appointment created successfully',
                'errors' => [],
                'data' => $appointment
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to create appointment', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while creating appointment',
                'errors' => [],
                'data' => null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateAppointment(string $uuid, array $data): array
    {
        try {
            $appointment = $this->appointmentRepository->findByUuid($uuid);
            
            if (!$appointment) {
                return [
                    'success' => false,
                    'message' => 'Appointment not found',
                    'errors' => [],
                    'data' => null
                ];
            }

            // Don't allow updates if appointment is completed or cancelled
            if ($appointment->isCompleted() || $appointment->status === Appointment::STATUS_CANCELLED) {
                return [
                    'success' => false,
                    'message' => 'Cannot update a completed or cancelled appointment',
                    'errors' => [],
                    'data' => null
                ];
            }

            // Check for scheduling conflicts if time is being changed
            if (isset($data['scheduled_start_time']) || isset($data['duration_minutes'])) {
                $startTime = $data['scheduled_start_time'] ?? $appointment->scheduled_start_time;
                $duration = $data['duration_minutes'] ?? $appointment->duration_minutes;
                $endTime = Carbon::parse($startTime)->addMinutes($duration);

                $conflict = $this->appointmentRepository->hasSchedulingConflict(
                    $data['facility_id'] ?? $appointment->facility_id,
                    $data['provider_staff_id'] ?? $appointment->provider_staff_id,
                    Carbon::parse($startTime),
                    $endTime,
                    $appointment->id
                );

                if ($conflict) {
                    return [
                        'success' => false,
                        'message' => 'Scheduling conflict detected',
                        'errors' => ['scheduled_start_time' => ['This time slot is already booked']],
                        'data' => null
                    ];
                }
            }

            // Update appointment
            $updatedAppointment = $this->appointmentRepository->update($appointment, $data);
            
            if (!$updatedAppointment) {
                return [
                    'success' => false,
                    'message' => 'Failed to update appointment',
                    'errors' => [],
                    'data' => null
                ];
            }

            Log::info('Service: Appointment updated successfully', [
                'appointment_id' => $updatedAppointment->id,
                'uuid' => $uuid
            ]);

            return [
                'success' => true,
                'message' => 'Appointment updated successfully',
                'errors' => [],
                'data' => $updatedAppointment
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to update appointment', [
                'uuid' => $uuid,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while updating appointment',
                'errors' => [],
                'data' => null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAppointment(string $uuid): array
    {
        try {
            $appointment = $this->appointmentRepository->findByUuid($uuid);
            
            if (!$appointment) {
                return [
                    'success' => false,
                    'message' => 'Appointment not found',
                    'errors' => [],
                    'data' => null
                ];
            }

            // Don't allow deletion if appointment is in progress or completed
            if ($appointment->isInProgress() || $appointment->isCompleted()) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete an appointment that is in progress or completed',
                    'errors' => [],
                    'data' => null
                ];
            }

            $deleted = $this->appointmentRepository->delete($appointment);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete appointment',
                    'errors' => [],
                    'data' => null
                ];
            }

            Log::info('Service: Appointment deleted successfully', ['uuid' => $uuid]);

            return [
                'success' => true,
                'message' => 'Appointment deleted successfully',
                'errors' => [],
                'data' => null
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to delete appointment', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while deleting appointment',
                'errors' => [],
                'data' => null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cancelAppointment(string $uuid, string $reason): array
    {
        try {
            $appointment = $this->appointmentRepository->findByUuid($uuid);
            
            if (!$appointment) {
                return [
                    'success' => false,
                    'message' => 'Appointment not found',
                    'errors' => [],
                    'data' => null
                ];
            }

            // Check if appointment can be cancelled
            if (!$appointment->isCancellable()) {
                return [
                    'success' => false,
                    'message' => 'Appointment cannot be cancelled at this time',
                    'errors' => [],
                    'data' => null
                ];
            }

            $updatedAppointment = $this->appointmentRepository->updateStatus(
                $appointment,
                Appointment::STATUS_CANCELLED,
                ['cancellation_reason' => $reason]
            );

            Log::info('Service: Appointment cancelled successfully', [
                'uuid' => $uuid,
                'reason' => $reason
            ]);

            // TODO: Send cancellation notification

            return [
                'success' => true,
                'message' => 'Appointment cancelled successfully',
                'errors' => [],
                'data' => $updatedAppointment
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to cancel appointment', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while cancelling appointment',
                'errors' => [],
                'data' => null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function confirmAppointment(string $uuid): array
    {
        try {
            $appointment = $this->appointmentRepository->findByUuid($uuid);
            
            if (!$appointment) {
                return [
                    'success' => false,
                    'message' => 'Appointment not found',
                    'errors' => [],
                    'data' => null
                ];
            }

            // Only scheduled appointments can be confirmed
            if ($appointment->status !== Appointment::STATUS_SCHEDULED) {
                return [
                    'success' => false,
                    'message' => 'Only scheduled appointments can be confirmed',
                    'errors' => [],
                    'data' => null
                ];
            }

            $updatedAppointment = $this->appointmentRepository->updateStatus(
                $appointment,
                Appointment::STATUS_CONFIRMED
            );

            Log::info('Service: Appointment confirmed successfully', ['uuid' => $uuid]);

            // TODO: Send confirmation notification

            return [
                'success' => true,
                'message' => 'Appointment confirmed successfully',
                'errors' => [],
                'data' => $updatedAppointment
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to confirm appointment', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while confirming appointment',
                'errors' => [],
                'data' => null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function checkInAppointment(string $uuid): array
    {
        try {
            $appointment = $this->appointmentRepository->findByUuid($uuid);
            
            if (!$appointment) {
                return [
                    'success' => false,
                    'message' => 'Appointment not found',
                    'errors' => [],
                    'data' => null
                ];
            }

            // Only confirmed appointments can be checked in
            if ($appointment->status !== Appointment::STATUS_CONFIRMED) {
                return [
                    'success' => false,
                    'message' => 'Only confirmed appointments can be checked in',
                    'errors' => [],
                    'data' => null
                ];
            }

            // Check if it's too early to check in (more than 30 minutes before)
            if ($appointment->scheduled_start_time->diffInMinutes(now()) > 30) {
                return [
                    'success' => false,
                    'message' => 'Too early to check in. Please check in within 30 minutes of your appointment',
                    'errors' => [],
                    'data' => null
                ];
            }

            $updatedAppointment = $this->appointmentRepository->updateStatus(
                $appointment,
                Appointment::STATUS_CHECKED_IN
            );

            Log::info('Service: Appointment checked in successfully', ['uuid' => $uuid]);

            return [
                'success' => true,
                'message' => 'Checked in successfully',
                'errors' => [],
                'data' => $updatedAppointment
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to check in appointment', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while checking in',
                'errors' => [],
                'data' => null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function completeAppointment(string $uuid): array
    {
        try {
            $appointment = $this->appointmentRepository->findByUuid($uuid);
            
            if (!$appointment) {
                return [
                    'success' => false,
                    'message' => 'Appointment not found',
                    'errors' => [],
                    'data' => null
                ];
            }

            // Only checked-in or in-progress appointments can be completed
            if (!in_array($appointment->status, [
                Appointment::STATUS_CHECKED_IN,
                Appointment::STATUS_IN_PROGRESS
            ])) {
                return [
                    'success' => false,
                    'message' => 'Only checked-in or in-progress appointments can be completed',
                    'errors' => [],
                    'data' => null
                ];
            }

            $updatedAppointment = $this->appointmentRepository->updateStatus(
                $appointment,
                Appointment::STATUS_COMPLETED
            );

            Log::info('Service: Appointment completed successfully', ['uuid' => $uuid]);

            // TODO: Create visit record from appointment
            // TODO: Send completion notification

            return [
                'success' => true,
                'message' => 'Appointment completed successfully',
                'errors' => [],
                'data' => $updatedAppointment
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to complete appointment', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while completing appointment',
                'errors' => [],
                'data' => null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rescheduleAppointment(string $uuid, array $scheduleData): array
    {
        try {
            $appointment = $this->appointmentRepository->findByUuid($uuid);
            
            if (!$appointment) {
                return [
                    'success' => false,
                    'message' => 'Appointment not found',
                    'errors' => [],
                    'data' => null
                ];
            }

            // Don't allow rescheduling if appointment is completed or cancelled
            if ($appointment->isCompleted() || $appointment->status === Appointment::STATUS_CANCELLED) {
                return [
                    'success' => false,
                    'message' => 'Cannot reschedule a completed or cancelled appointment',
                    'errors' => [],
                    'data' => null
                ];
            }

            // Validate required fields for rescheduling
            $validator = Validator::make($scheduleData, [
                'scheduled_start_time' => 'required|date|after:now',
                'duration_minutes' => 'required|integer|min:5|max:480'
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                    'data' => null
                ];
            }

            // Check for scheduling conflicts
            $conflict = $this->appointmentRepository->hasSchedulingConflict(
                $appointment->facility_id,
                $appointment->provider_staff_id,
                Carbon::parse($scheduleData['scheduled_start_time']),
                Carbon::parse($scheduleData['scheduled_start_time'])
                    ->addMinutes($scheduleData['duration_minutes']),
                $appointment->id
            );

            if ($conflict) {
                return [
                    'success' => false,
                    'message' => 'Scheduling conflict detected',
                    'errors' => ['scheduled_start_time' => ['This time slot is already booked']],
                    'data' => null
                ];
            }

            // Update with rescheduled data
            $updateData = array_merge($scheduleData, ['status' => Appointment::STATUS_RESCHEDULED]);
            $updatedAppointment = $this->appointmentRepository->update($appointment, $updateData);
            
            if (!$updatedAppointment) {
                return [
                    'success' => false,
                    'message' => 'Failed to reschedule appointment',
                    'errors' => [],
                    'data' => null
                ];
            }

            Log::info('Service: Appointment rescheduled successfully', [
                'uuid' => $uuid,
                'new_time' => $scheduleData['scheduled_start_time']
            ]);

            // TODO: Send rescheduling notification

            return [
                'success' => true,
                'message' => 'Appointment rescheduled successfully',
                'errors' => [],
                'data' => $updatedAppointment
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to reschedule appointment', [
                'uuid' => $uuid,
                'data' => $scheduleData,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while rescheduling appointment',
                'errors' => [],
                'data' => null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPatientAppointments(int $patientId, array $filters = []): Collection
    {
        try {
            $filters['patient_id'] = $patientId;
            return $this->appointmentRepository->getByPatient($patientId, $filters);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get patient appointments', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getProviderAppointments(int $providerId, array $filters = []): Collection
    {
        try {
            $filters['provider_staff_id'] = $providerId;
            return $this->appointmentRepository->getByProvider($providerId, $filters);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get provider appointments', [
                'provider_id' => $providerId,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFacilityAppointments(int $facilityId, array $filters = []): Collection
    {
        try {
            $filters['facility_id'] = $facilityId;
            return $this->appointmentRepository->getByFacility($facilityId, $filters);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get facility appointments', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getUpcomingAppointments(array $filters = []): Collection
    {
        try {
            return $this->appointmentRepository->getUpcoming($filters);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get upcoming appointments', [
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function checkAvailability(array $availabilityData): array
    {
        try {
            $validator = Validator::make($availabilityData, [
                'facility_id' => 'required|integer|exists:facilities,id',
                'provider_staff_id' => 'required|integer|exists:staff,id',
                'date' => 'required|date|after_or_equal:today',
                'duration_minutes' => 'required|integer|min:15|max:240'
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                    'data' => null
                ];
            }

            $date = Carbon::parse($availabilityData['date']);
            $duration = $availabilityData['duration_minutes'];
            
            // Get existing appointments for the day
            $appointments = $this->appointmentRepository->getByDateRange(
                $date->copy()->startOfDay(),
                $date->copy()->endOfDay(),
                [
                    'facility_id' => $availabilityData['facility_id'],
                    'provider_staff_id' => $availabilityData['provider_staff_id']
                ]
            );

            // Define working hours (9 AM to 5 PM)
            $startHour = 9;
            $endHour = 17;
            $interval = 30; // 30-minute intervals

            $availableSlots = [];
            $currentTime = $date->copy()->setTime($startHour, 0);

            while ($currentTime->hour < $endHour) {
                $slotStart = $currentTime->copy();
                $slotEnd = $slotStart->copy()->addMinutes($duration);

                // Check if slot end time exceeds working hours
                if ($slotEnd->hour >= $endHour && $slotEnd->minute > 0) {
                    $currentTime->addMinutes($interval);
                    continue;
                }

                // Check for conflicts with existing appointments
                $hasConflict = false;
                foreach ($appointments as $appointment) {
                    if (
                        ($slotStart >= $appointment->scheduled_start_time && 
                         $slotStart < $appointment->scheduled_end_time) ||
                        ($slotEnd > $appointment->scheduled_start_time && 
                         $slotEnd <= $appointment->scheduled_end_time) ||
                        ($slotStart <= $appointment->scheduled_start_time && 
                         $slotEnd >= $appointment->scheduled_end_time)
                    ) {
                        $hasConflict = true;
                        break;
                    }
                }

                if (!$hasConflict && $slotStart > now()) {
                    $availableSlots[] = [
                        'start_time' => $slotStart->toDateTimeString(),
                        'end_time' => $slotEnd->toDateTimeString(),
                        'duration_minutes' => $duration
                    ];
                }

                $currentTime->addMinutes($interval);
            }

            return [
                'success' => true,
                'message' => 'Availability retrieved successfully',
                'errors' => [],
                'data' => [
                    'date' => $date->toDateString(),
                    'available_slots' => $availableSlots,
                    'total_slots' => count($availableSlots)
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to check availability', [
                'data' => $availabilityData,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while checking availability',
                'errors' => [],
                'data' => null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function sendReminder(string $uuid): array
    {
        try {
            $appointment = $this->appointmentRepository->findByUuid($uuid);
            
            if (!$appointment) {
                return [
                    'success' => false,
                    'message' => 'Appointment not found',
                    'errors' => [],
                    'data' => null
                ];
            }

            // Only send reminders for upcoming scheduled or confirmed appointments
            if (!$appointment->isUpcoming()) {
                return [
                    'success' => false,
                    'message' => 'Reminders can only be sent for upcoming scheduled or confirmed appointments',
                    'errors' => [],
                    'data' => null
                ];
            }

            // Don't send reminder if already sent within last 24 hours
            if ($appointment->reminder_sent && 
                $appointment->reminder_sent_at &&
                $appointment->reminder_sent_at->diffInHours(now()) < 24) {
                return [
                    'success' => false,
                    'message' => 'Reminder already sent within last 24 hours',
                    'errors' => [],
                    'data' => null
                ];
            }

            // TODO: Implement actual reminder sending (email, SMS, etc.)
            // For now, just update the reminder flag
            $updatedAppointment = $this->appointmentRepository->update($appointment, [
                'reminder_sent' => true,
                'reminder_sent_at' => now()
            ]);

            Log::info('Service: Appointment reminder sent successfully', ['uuid' => $uuid]);

            return [
                'success' => true,
                'message' => 'Reminder sent successfully',
                'errors' => [],
                'data' => $updatedAppointment
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to send reminder', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while sending reminder',
                'errors' => [],
                'data' => null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAppointmentStatistics(array $filters = []): array
    {
        try {
            $stats = $this->appointmentRepository->getStatistics($filters);
            
            return [
                'success' => true,
                'message' => 'Statistics retrieved successfully',
                'errors' => [],
                'data' => $stats
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to get appointment statistics', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while retrieving statistics',
                'errors' => [],
                'data' => null
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateAppointmentData(array $data): array
    {
        $validator = Validator::make($data, [
            'facility_id' => 'required|integer|exists:facilities,id',
            'patient_id' => 'required|integer|exists:patients,id',
            'provider_staff_id' => 'required|integer|exists:staff,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'appointment_type' => 'required|in:' . implode(',', [
                'new_patient_consultation',
                'followup_visit',
                'annual_physical',
                'procedure',
                'diagnostic_test',
                'therapy_session',
                'telehealth',
                'vaccination',
                'consultation'
            ]),
            'scheduled_start_time' => 'required|date|after:now',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'reason_for_visit' => 'nullable|string|max:1000',
            'requested_services' => 'nullable|array',
            'requested_services.*' => 'string',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->toArray()
            ];
        }

        // Additional business validation
        $errors = [];

        // Check if scheduled time is within working hours (9 AM to 5 PM)
        $scheduledTime = Carbon::parse($data['scheduled_start_time']);
        $startHour = $scheduledTime->copy()->setTime(9, 0);
        $endHour = $scheduledTime->copy()->setTime(17, 0);

        if ($scheduledTime->hour < 9 || ($scheduledTime->hour >= 17 && $scheduledTime->minute > 0)) {
            $errors['scheduled_start_time'] = ['Appointments must be scheduled between 9 AM and 5 PM'];
        }

        // Check if duration is reasonable for appointment type
        $typeDurationLimits = [
            'new_patient_consultation' => ['min' => 30, 'max' => 60],
            'followup_visit' => ['min' => 15, 'max' => 30],
            'annual_physical' => ['min' => 45, 'max' => 90],
            'procedure' => ['min' => 30, 'max' => 240],
            'diagnostic_test' => ['min' => 15, 'max' => 120],
            'therapy_session' => ['min' => 45, 'max' => 60],
            'telehealth' => ['min' => 20, 'max' => 45],
            'vaccination' => ['min' => 10, 'max' => 30],
            'consultation' => ['min' => 20, 'max' => 60],
        ];

        $duration = $data['duration_minutes'];
        $type = $data['appointment_type'];
        
        if (isset($typeDurationLimits[$type])) {
            $limits = $typeDurationLimits[$type];
            if ($duration < $limits['min'] || $duration > $limits['max']) {
                $errors['duration_minutes'] = ["Duration for $type should be between {$limits['min']} and {$limits['max']} minutes"];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}