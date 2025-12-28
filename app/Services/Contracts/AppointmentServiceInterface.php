<?php

namespace App\Services\Contracts;

use App\Models\Appointment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface AppointmentServiceInterface
{
    /**
     * Get all appointments with pagination
     */
    public function getAllAppointments(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get appointment by UUID
     */
    public function getAppointmentByUuid(string $uuid): ?Appointment;

    /**
     * Create a new appointment
     */
    public function createAppointment(array $data): array;

    /**
     * Update an existing appointment
     */
    public function updateAppointment(string $uuid, array $data): array;

    /**
     * Delete an appointment (soft delete)
     */
    public function deleteAppointment(string $uuid): array;

    /**
     * Cancel an appointment
     */
    public function cancelAppointment(string $uuid, string $reason): array;

    /**
     * Confirm an appointment
     */
    public function confirmAppointment(string $uuid): array;

    /**
     * Check-in for an appointment
     */
    public function checkInAppointment(string $uuid): array;

    /**
     * Mark appointment as completed
     */
    public function completeAppointment(string $uuid): array;

    /**
     * Reschedule an appointment
     */
    public function rescheduleAppointment(string $uuid, array $scheduleData): array;

    /**
     * Get appointments by patient
     */
    public function getPatientAppointments(int $patientId, array $filters = []): Collection;

    /**
     * Get appointments by provider
     */
    public function getProviderAppointments(int $providerId, array $filters = []): Collection;

    /**
     * Get appointments by facility
     */
    public function getFacilityAppointments(int $facilityId, array $filters = []): Collection;

    /**
     * Get upcoming appointments
     */
    public function getUpcomingAppointments(array $filters = []): Collection;

    /**
     * Check scheduling availability
     */
    public function checkAvailability(array $availabilityData): array;

    /**
     * Send appointment reminder
     */
    public function sendReminder(string $uuid): array;

    /**
     * Get appointment statistics
     */
    public function getAppointmentStatistics(array $filters = []): array;

    /**
     * Validate appointment data before creation
     */
    public function validateAppointmentData(array $data): array;
}