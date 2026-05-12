<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\AppointmentCollection;
use App\Services\Contracts\AppointmentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AppointmentController extends Controller
{
    /**
     * Appointment service instance
     */
    private AppointmentServiceInterface $appointmentService;

    /**
     * Constructor
     */
    public function __construct(AppointmentServiceInterface $appointmentService)
    {
        $this->appointmentService = $appointmentService;
        
        // Apply policy authorization middleware
        $this->authorizeResource(\App\Models\Appointment::class, 'appointment');
    }

    /**
     * Display a listing of appointments.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get filters from request
            $filters = $request->only([
                'facility_id',
                'patient_id',
                'provider_staff_id',
                'status',
                'appointment_type',
                'date_from',
                'date_to',
                'search',
                'upcoming'
            ]);

            $user = $request->user();
            if ($user && $user->hasRole('patient') && $user->patientProfile) {
                $filters['patient_id'] = $user->patientProfile->id;
            }

            // Get paginated appointments
            $perPage = $request->input('per_page', 15);
            $appointments = $this->appointmentService->getAllAppointments($filters, $perPage);

            // Return collection with pagination
            return response()->json([
                'success' => true,
                'message' => 'Appointments retrieved successfully',
                'data' => new AppointmentCollection($appointments),
                'meta' => [
                    'total' => $appointments->total(),
                    'per_page' => $appointments->perPage(),
                    'current_page' => $appointments->currentPage(),
                    'last_page' => $appointments->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to retrieve appointments', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve appointments',
                'errors' => [],
                'data' => null
            ], 500);
        }
    }

    /**
     * Store a newly created appointment.
     */
    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        try {
            // Get validated data
            $data = $request->validated();
            
            // Create appointment via service
            $result = $this->appointmentService->createAppointment($data);

            // Return appropriate response
            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Appointment created successfully',
                'data' => new AppointmentResource($result['data']),
                'meta' => [
                    'created_at' => now()->toISOString(),
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to create appointment', [
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while creating appointment',
                'errors' => [],
                'data' => null
            ], 500);
        }
    }

    /**
     * Display the specified appointment.
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            // Find appointment by UUID
            $appointment = $this->appointmentService->getAppointmentByUuid($uuid);

            if (!$appointment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Appointment not found',
                    'errors' => [],
                    'data' => null
                ], 404);
            }

            // Authorize viewing (already done by authorizeResource, but double-check)
            $this->authorize('view', $appointment);

            return response()->json([
                'success' => true,
                'message' => 'Appointment retrieved successfully',
                'data' => new AppointmentResource($appointment),
                'meta' => [
                    'retrieved_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this appointment',
                'errors' => [],
                'data' => null
            ], 403);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to retrieve appointment', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve appointment',
                'errors' => [],
                'data' => null
            ], 500);
        }
    }

    /**
     * Update the specified appointment.
     */
    public function update(UpdateAppointmentRequest $request, string $uuid): JsonResponse
    {
        try {
            // Get validated data
            $data = $request->validated();
            
            // Update appointment via service
            $result = $this->appointmentService->updateAppointment($uuid, $data);

            // Return appropriate response
            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Appointment updated successfully',
                'data' => new AppointmentResource($result['data']),
                'meta' => [
                    'updated_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this appointment',
                'errors' => [],
                'data' => null
            ], 403);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to update appointment', [
                'uuid' => $uuid,
                'data' => $request->all(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while updating appointment',
                'errors' => [],
                'data' => null
            ], 500);
        }
    }

    /**
     * Remove the specified appointment.
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            // Delete appointment via service
            $result = $this->appointmentService->deleteAppointment($uuid);

            // Return appropriate response
            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Appointment deleted successfully',
                'data' => null,
                'meta' => [
                    'deleted_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this appointment',
                'errors' => [],
                'data' => null
            ], 403);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to delete appointment', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while deleting appointment',
                'errors' => [],
                'data' => null
            ], 500);
        }
    }

    /**
     * Cancel an appointment.
     */
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        try {
            // Validate cancellation request
            $request->validate([
                'cancellation_reason' => 'required|string|max:500'
            ]);

            // Authorize cancellation
            $appointment = $this->appointmentService->getAppointmentByUuid($uuid);
            if (!$appointment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Appointment not found',
                    'errors' => [],
                    'data' => null
                ], 404);
            }

            $this->authorize('cancel', $appointment);

            // Cancel appointment via service
            $result = $this->appointmentService->cancelAppointment(
                $uuid,
                $request->input('cancellation_reason')
            );

            // Return appropriate response
            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Appointment cancelled successfully',
                'data' => new AppointmentResource($result['data']),
                'meta' => [
                    'cancelled_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to cancel this appointment',
                'errors' => [],
                'data' => null
            ], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => null
            ], 422);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to cancel appointment', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while cancelling appointment',
                'errors' => [],
                'data' => null
            ], 500);
        }
    }

    /**
     * Confirm an appointment.
     */
    public function confirm(string $uuid): JsonResponse
    {
        try {
            // Authorize confirmation
            $appointment = $this->appointmentService->getAppointmentByUuid($uuid);
            if (!$appointment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Appointment not found',
                    'errors' => [],
                    'data' => null
                ], 404);
            }

            $this->authorize('confirm', $appointment);

            // Confirm appointment via service
            $result = $this->appointmentService->confirmAppointment($uuid);

            // Return appropriate response
            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Appointment confirmed successfully',
                'data' => new AppointmentResource($result['data']),
                'meta' => [
                    'confirmed_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to confirm this appointment',
                'errors' => [],
                'data' => null
            ], 403);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to confirm appointment', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while confirming appointment',
                'errors' => [],
                'data' => null
            ], 500);
        }
    }

    /**
     * Check in to an appointment.
     */
    public function checkIn(string $uuid): JsonResponse
    {
        try {
            // Authorize check-in
            $appointment = $this->appointmentService->getAppointmentByUuid($uuid);
            if (!$appointment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Appointment not found',
                    'errors' => [],
                    'data' => null
                ], 404);
            }

            $this->authorize('checkIn', $appointment);

            // Check in via service
            $result = $this->appointmentService->checkInAppointment($uuid);

            // Return appropriate response
            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Checked in successfully',
                'data' => new AppointmentResource($result['data']),
                'meta' => [
                    'checked_in_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to check in for this appointment',
                'errors' => [],
                'data' => null
            ], 403);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to check in for appointment', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while checking in',
                'errors' => [],
                'data' => null
            ], 500);
        }
    }

    /**
     * Complete an appointment.
     */
    public function complete(string $uuid): JsonResponse
    {
        try {
            // Authorize completion
            $appointment = $this->appointmentService->getAppointmentByUuid($uuid);
            if (!$appointment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Appointment not found',
                    'errors' => [],
                    'data' => null
                ], 404);
            }

            $this->authorize('complete', $appointment);

            // Complete appointment via service
            $result = $this->appointmentService->completeAppointment($uuid);

            // Return appropriate response
            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Appointment completed successfully',
                'data' => new AppointmentResource($result['data']),
                'meta' => [
                    'completed_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to complete this appointment',
                'errors' => [],
                'data' => null
            ], 403);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to complete appointment', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while completing appointment',
                'errors' => [],
                'data' => null
            ], 500);
        }
    }

    /**
     * Reschedule an appointment.
     */
    public function reschedule(Request $request, string $uuid): JsonResponse
    {
        try {
            // Validate reschedule request
            $validated = $request->validate([
                'scheduled_start_time' => 'required|date|after:now',
                'duration_minutes' => 'required|integer|min:5|max:480'
            ]);

            // Authorize rescheduling
            $appointment = $this->appointmentService->getAppointmentByUuid($uuid);
            if (!$appointment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Appointment not found',
                    'errors' => [],
                    'data' => null
                ], 404);
            }

            $this->authorize('reschedule', $appointment);

            // Reschedule via service
            $result = $this->appointmentService->rescheduleAppointment($uuid, $validated);

            // Return appropriate response
            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Appointment rescheduled successfully',
                'data' => new AppointmentResource($result['data']),
                'meta' => [
                    'rescheduled_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to reschedule this appointment',
                'errors' => [],
                'data' => null
            ], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => null
            ], 422);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to reschedule appointment', [
                'uuid' => $uuid,
                'data' => $request->all(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while rescheduling appointment',
                'errors' => [],
                'data' => null
            ], 500);
        }
    }

    /**
     * Send appointment reminder.
     */
    public function sendReminder(string $uuid): JsonResponse
    {
        try {
            // Authorize reminder sending
            $appointment = $this->appointmentService->getAppointmentByUuid($uuid);
            if (!$appointment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Appointment not found',
                    'errors' => [],
                    'data' => null
                ], 404);
            }

            $this->authorize('sendReminder', $appointment);

            // Send reminder via service
            $result = $this->appointmentService->sendReminder($uuid);

            // Return appropriate response
            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Reminder sent successfully',
                'data' => new AppointmentResource($result['data']),
                'meta' => [
                    'reminder_sent_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to send reminders for this appointment',
                'errors' => [],
                'data' => null
            ], 403);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to send appointment reminder', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while sending reminder',
                'errors' => [],
                'data' => null
            ], 500);
        }
    }

    /**
     * Check appointment availability.
     */
    public function availability(Request $request): JsonResponse
    {
        try {
            // Validate availability request
            $validated = $request->validate([
                'facility_id' => 'required|integer|exists:facilities,id',
                'provider_staff_id' => 'required|integer|exists:staff,id',
                'date' => 'required|date|after_or_equal:today',
                'duration_minutes' => 'required|integer|min:15|max:240'
            ]);

            // Check availability via service
            $result = $this->appointmentService->checkAvailability($validated);

            // Return appropriate response
            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => null
            ], 422);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to check appointment availability', [
                'data' => $request->all(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while checking availability',
                'errors' => [],
                'data' => null
            ], 500);
        }
    }

    /**
     * Get appointment statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            // Get filters from request
            $filters = $request->only([
                'facility_id',
                'date_from',
                'date_to'
            ]);

            // Get statistics via service
            $result = $this->appointmentService->getAppointmentStatistics($filters);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Controller: Failed to get appointment statistics', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving statistics',
                'errors' => [],
                'data' => null
            ], 500);
        }
    }
}