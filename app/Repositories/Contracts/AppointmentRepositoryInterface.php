<?php

namespace App\Repositories\Contracts;

use App\Models\Appointment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

interface AppointmentRepositoryInterface
{
    /**
     * Find appointment by ID
     */
    public function findById(int $id): ?Appointment;

    /**
     * Find appointment by UUID
     */
    public function findByUuid(string $uuid): ?Appointment;

    /**
     * Get all appointments with pagination
     */
    public function all(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Create a new appointment
     */
    public function create(array $data): Appointment;

    /**
     * Update an appointment
     */
    public function update(Appointment $appointment, array $data): Appointment;

    /**
     * Delete an appointment (soft delete)
     */
    public function delete(Appointment $appointment): bool;

    /**
     * Force delete an appointment
     */
    public function forceDelete(Appointment $appointment): bool;

    /**
     * Restore a soft-deleted appointment
     */
    public function restore(Appointment $appointment): bool;

    /**
     * Get appointments by facility
     */
    public function getByFacility(int $facilityId, array $filters = []): Collection;

    /**
     * Get appointments by patient
     */
    public function getByPatient(int $patientId, array $filters = []): Collection;

    /**
     * Get appointments by provider
     */
    public function getByProvider(int $providerId, array $filters = []): Collection;

    /**
     * Get upcoming appointments
     */
    public function getUpcoming(array $filters = []): Collection;

    /**
     * Get appointments for a specific date range
     */
    public function getByDateRange(Carbon $startDate, Carbon $endDate, array $filters = []): Collection;

    /**
     * Update appointment status
     */
    public function updateStatus(Appointment $appointment, string $status, array $additionalData = []): Appointment;

    /**
     * Check for scheduling conflicts
     */
    public function hasSchedulingConflict(
        int $facilityId,
        int $providerId,
        Carbon $startTime,
        Carbon $endTime,
        ?int $excludeAppointmentId = null
    ): bool;

    /**
     * Get appointment statistics
     */
    public function getStatistics(array $filters = []): array;
}