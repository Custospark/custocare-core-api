<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FacilityTask\StoreFacilityTaskRequest;
use App\Http\Requests\FacilityTask\UpdateFacilityTaskRequest;
use App\Services\FacilityTask\FacilityTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FacilityTaskController extends Controller
{
    public function __construct(private FacilityTaskService $service) {}

    /** GET /api/facility-tasks — Assign task / facility board */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'assigned_to_user_id' => ['nullable', 'integer'],
            'assigned_by_user_id' => ['nullable', 'integer'],
            'ward_id' => ['nullable', 'integer'],
            'category' => ['nullable', 'string'],
            'priority' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:200'],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $facilityId = (int) $request->query('facility_id');
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $filters = array_filter([
            'assigned_to_user_id' => $request->query('assigned_to_user_id'),
            'assigned_by_user_id' => $request->query('assigned_by_user_id'),
            'ward_id' => $request->query('ward_id'),
            'category' => $request->query('category'),
            'priority' => $request->query('priority'),
            'status' => $request->query('status'),
            'search' => $request->query('search'),
            'due_from' => $request->query('due_from'),
            'due_to' => $request->query('due_to'),
        ], fn ($v) => $v !== null && $v !== '');

        $paginator = $this->service->paginateIndex($facilityId, $filters, $perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /** GET /api/facility-tasks/my — current user’s tasks */
    public function myTasks(Request $request): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'status' => ['nullable', 'string'],
            'priority' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $facilityId = (int) $request->query('facility_id');
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $filters = array_filter([
            'status' => $request->query('status'),
            'priority' => $request->query('priority'),
        ], fn ($v) => $v !== null && $v !== '');

        $userId = (int) Auth::id();
        $paginator = $this->service->paginateMyTasks($facilityId, $userId, $filters, $perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /** GET /api/facility-tasks/history — completed / cancelled */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'assigned_to_user_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $facilityId = (int) $request->query('facility_id');
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $filters = array_filter([
            'assigned_to_user_id' => $request->query('assigned_to_user_id'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'search' => $request->query('search'),
        ], fn ($v) => $v !== null && $v !== '');

        $paginator = $this->service->paginateHistory($facilityId, $filters, $perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreFacilityTaskRequest $request): JsonResponse
    {
        $task = $this->service->create($request->validated(), (int) Auth::id());

        return response()->json([
            'message' => 'Task created successfully.',
            'data' => $task,
        ], 201);
    }

    public function show(Request $request, int $task): JsonResponse
    {
        $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
        ]);

        $facilityId = (int) $request->query('facility_id');
        $model = $this->service->findForFacilityWithRelations($task, $facilityId, true);

        if (! $model) {
            return response()->json([
                'message' => 'Task not found for this facility.',
            ], 404);
        }

        return response()->json(['data' => $model]);
    }

    public function update(UpdateFacilityTaskRequest $request, int $task): JsonResponse
    {
        $facilityId = (int) $request->validated()['facility_id'];

        $model = $this->service->findForFacility($task, $facilityId);
        if (! $model) {
            return response()->json([
                'message' => 'Task not found for this facility.',
            ], 404);
        }

        $payload = collect($request->validated())->except('facility_id')->all();

        $updated = $this->service->update($model, $payload, (int) Auth::id());

        return response()->json([
            'message' => 'Task updated successfully.',
            'data' => $updated,
        ]);
    }
}
