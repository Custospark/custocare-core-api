<?php

declare(strict_types=1);

namespace App\Services\FacilityTask;

use App\Models\FacilityTask;
use App\Models\FacilityTaskEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FacilityTaskService
{
    public function findForFacility(int $taskId, int $facilityId): ?FacilityTask
    {
        return FacilityTask::query()
            ->whereKey($taskId)
            ->where('facility_id', $facilityId)
            ->first();
    }

    public function findForFacilityWithRelations(int $taskId, int $facilityId, bool $withEvents = false): ?FacilityTask
    {
        $q = FacilityTask::query()
            ->whereKey($taskId)
            ->where('facility_id', $facilityId)
            ->with([
                'assignedTo:id,display_name,first_name,last_name',
                'assignedBy:id,display_name,first_name,last_name',
                'ward:id,name,code',
                'createdBy:id,display_name',
                'updatedBy:id,display_name',
            ]);

        if ($withEvents) {
            $q->with(['events' => function ($rel) {
                $rel->with('actor:id,display_name,first_name,last_name')
                    ->orderByDesc('id')
                    ->limit(100);
            }]);
        }

        return $q->first();
    }

    protected function baseListQuery(int $facilityId)
    {
        return FacilityTask::query()
            ->where('facility_id', $facilityId)
            ->with([
                'assignedTo:id,display_name,first_name,last_name',
                'assignedBy:id,display_name,first_name,last_name',
                'ward:id,name,code',
            ]);
    }

    /**
     * Facility task board / assign-task views — filterable list.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateIndex(int $facilityId, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $q = $this->baseListQuery($facilityId);

        if (! empty($filters['assigned_to_user_id'])) {
            $q->where('assigned_to_user_id', (int) $filters['assigned_to_user_id']);
        }

        if (! empty($filters['assigned_by_user_id'])) {
            $q->where('assigned_by_user_id', (int) $filters['assigned_by_user_id']);
        }

        if (! empty($filters['ward_id'])) {
            $q->where('ward_id', (int) $filters['ward_id']);
        }

        if (! empty($filters['category'])) {
            $q->where('category', $filters['category']);
        }

        if (! empty($filters['priority'])) {
            $q->where('priority', $filters['priority']);
        }

        if (! empty($filters['status'])) {
            $statuses = is_array($filters['status'])
                ? $filters['status']
                : array_map('trim', explode(',', (string) $filters['status']));
            $q->whereIn('status', $statuses);
        }

        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $q->where(function ($w) use ($term) {
                $w->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        if (! empty($filters['due_from'])) {
            $q->where('due_at', '>=', $filters['due_from']);
        }

        if (! empty($filters['due_to'])) {
            $q->where('due_at', '<=', $filters['due_to']);
        }

        return $q->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * Tasks assigned to the current user (My Tasks).
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateMyTasks(int $facilityId, int $assigneeUserId, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $q = $this->baseListQuery($facilityId)
            ->where('assigned_to_user_id', $assigneeUserId);

        $statuses = $filters['status'] ?? ['pending', 'in_progress'];
        if (is_string($statuses)) {
            $statuses = array_map('trim', explode(',', $statuses));
        }
        $q->whereIn('status', $statuses);

        if (! empty($filters['priority'])) {
            $q->where('priority', $filters['priority']);
        }

        return $q
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('due_at')
            ->orderByRaw("FIELD(priority, 'urgent','high','normal','low')")
            ->paginate($perPage);
    }

    /**
     * Completed / cancelled tasks (Task History).
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateHistory(int $facilityId, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $q = $this->baseListQuery($facilityId)
            ->whereIn('status', ['completed', 'cancelled']);

        if (! empty($filters['assigned_to_user_id'])) {
            $q->where('assigned_to_user_id', (int) $filters['assigned_to_user_id']);
        }

        if (! empty($filters['from'])) {
            $from = $filters['from'];
            $q->where(function ($w) use ($from) {
                $w->where('completed_at', '>=', $from)
                    ->orWhere('cancelled_at', '>=', $from);
            });
        }

        if (! empty($filters['to'])) {
            $to = $filters['to'];
            $q->where(function ($w) use ($to) {
                $w->where('completed_at', '<=', $to)
                    ->orWhere('cancelled_at', '<=', $to);
            });
        }

        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $q->where(function ($w) use ($term) {
                $w->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        return $q->orderByDesc(DB::raw('COALESCE(completed_at, cancelled_at, updated_at)'))
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data  validated payload
     */
    public function create(array $data, int $actorUserId): FacilityTask
    {
        return DB::transaction(function () use ($data, $actorUserId) {
            $task = new FacilityTask;
            $task->fill([
                'facility_id' => (int) $data['facility_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? 'other',
                'priority' => $data['priority'] ?? 'normal',
                'status' => $data['status'] ?? 'pending',
                'due_at' => $data['due_at'] ?? null,
                'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
                'assigned_by_user_id' => $actorUserId,
                'ward_id' => $data['ward_id'] ?? null,
                'visit_uuid' => $data['visit_uuid'] ?? null,
                'created_by_user_id' => $actorUserId,
                'updated_by_user_id' => $actorUserId,
            ]);

            if (($task->status ?? '') === 'in_progress') {
                $task->started_at = now();
            }

            $task->save();

            $this->recordEvent($task, $actorUserId, 'created', [
                'title' => $task->title,
                'assigned_to_user_id' => $task->assigned_to_user_id,
            ]);

            if ($task->assigned_to_user_id) {
                $this->recordEvent($task, $actorUserId, 'assigned', [
                    'assigned_to_user_id' => $task->assigned_to_user_id,
                ]);
            }

            return $task->fresh([
                'assignedTo:id,display_name,first_name,last_name',
                'assignedBy:id,display_name,first_name,last_name',
                'ward:id,name,code',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated patch
     */
    public function update(FacilityTask $task, array $data, int $actorUserId): FacilityTask
    {
        return DB::transaction(function () use ($task, $data, $actorUserId) {
            $beforeAssignee = $task->assigned_to_user_id;
            $beforeStatus = $task->status;

            $fillable = [
                'title', 'description', 'category', 'priority', 'due_at',
                'assigned_to_user_id', 'ward_id', 'visit_uuid',
                'cancellation_reason', 'completion_notes',
            ];

            foreach ($fillable as $key) {
                if (array_key_exists($key, $data)) {
                    $task->{$key} = $data[$key];
                }
            }

            if (array_key_exists('status', $data)) {
                $newStatus = $data['status'];
                $task->status = $newStatus;

                if ($newStatus === 'in_progress' && ! $task->started_at) {
                    $task->started_at = now();
                }
                if ($newStatus === 'completed') {
                    $task->completed_at = now();
                    $task->cancelled_at = null;
                }
                if ($newStatus === 'cancelled') {
                    $task->cancelled_at = now();
                    $task->completed_at = null;
                }
                if ($newStatus === 'pending') {
                    $task->started_at = null;
                }
            }

            $task->updated_by_user_id = $actorUserId;
            $task->save();

            $assigneeChanged = $beforeAssignee !== $task->assigned_to_user_id;
            $statusChanged = array_key_exists('status', $data) && $beforeStatus !== $task->status;

            if ($assigneeChanged) {
                $this->recordEvent($task, $actorUserId, 'assigned', [
                    'from_user_id' => $beforeAssignee,
                    'to_user_id' => $task->assigned_to_user_id,
                ]);
            }

            if ($statusChanged) {
                $this->recordEvent($task, $actorUserId, 'status_changed', [
                    'from' => $beforeStatus,
                    'to' => $task->status,
                ]);
                if ($task->status === 'completed') {
                    $this->recordEvent($task, $actorUserId, 'completed', []);
                }
                if ($task->status === 'cancelled') {
                    $this->recordEvent($task, $actorUserId, 'cancelled', [
                        'reason' => $task->cancellation_reason,
                    ]);
                }
            }

            if (! $assigneeChanged && ! $statusChanged && $data !== []) {
                $this->recordEvent($task, $actorUserId, 'updated', [
                    'fields' => array_keys($data),
                ]);
            }

            return $task->fresh([
                'assignedTo:id,display_name,first_name,last_name',
                'assignedBy:id,display_name,first_name,last_name',
                'ward:id,name,code',
            ]);
        });
    }

    protected function recordEvent(FacilityTask $task, ?int $actorUserId, string $type, array $meta): void
    {
        FacilityTaskEvent::query()->create([
            'facility_task_id' => $task->id,
            'actor_user_id' => $actorUserId,
            'event_type' => $type,
            'meta' => $meta !== [] ? $meta : null,
        ]);
    }
}
