<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Compliance;

use App\Http\Controllers\Controller;
use App\Models\DsarRequest;
use App\Services\Compliance\DsarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DsarController extends Controller
{
    public function __construct(
        protected DsarService $dsarService
    ) {}

    /**
     * Staff logs a subject request (in-person, email, phone, portal).
     * Every request is registered regardless of outcome.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'request_type' => ['required', 'in:access,correction,erasure,restriction,objection'],
            'channel' => ['nullable', 'in:in_person,email,phone,portal'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['requested_by_staff_id'] = $request->user()?->staff?->id;

        $dsar = $this->dsarService->submit($validated);

        return response()->json([
            'success' => true,
            'message' => 'Request registered. Five-working-day clock started.',
            'data' => $dsar,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = DsarRequest::orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->boolean('overdue')) {
            $query->whereIn('status', ['received', 'in_review'])
                ->where('sla_due_at', '<', now());
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->integer('per_page', 15)),
        ]);
    }

    public function show(DsarRequest $dsarRequest): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $dsarRequest]);
    }

    public function fulfill(Request $request, DsarRequest $dsarRequest): JsonResponse
    {
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);

        try {
            $result = $this->dsarService->fulfill($dsarRequest, $validated['notes'] ?? '');
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Record that every disclosed-to entity was informed of the action.
     * Called after fulfill when correction/erasure must propagate.
     */
    public function notifyDownstream(DsarRequest $dsarRequest): JsonResponse
    {
        $result = $this->dsarService->markDownstreamNotified($dsarRequest);

        return response()->json([
            'success' => true,
            'message' => 'Downstream notification recorded.',
            'data' => $result,
        ]);
    }

    public function reject(Request $request, DsarRequest $dsarRequest): JsonResponse
    {
        $validated = $request->validate(['reasons' => ['required', 'string', 'max:2000']]);

        try {
            $result = $this->dsarService->reject($dsarRequest, $validated['reasons']);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $result]);
    }
}
