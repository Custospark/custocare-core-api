<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\HubSupportFaq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformHubSupportFaqController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_published'  => ['nullable', 'boolean'],
            'include_trash' => ['nullable', 'boolean'],
            'q'             => ['nullable', 'string', 'max:200'],
        ]);

        $query = HubSupportFaq::query()->orderBy('sort_order')->orderBy('id');

        if (! empty($validated['include_trash'])) {
            $query->withTrashed();
        }

        if (array_key_exists('is_published', $validated) && $validated['is_published'] !== null) {
            $query->where('is_published', (bool) $validated['is_published']);
        }

        if (! empty($validated['q'])) {
            $term = '%'.$validated['q'].'%';
            $query->where(function ($q) use ($term): void {
                $q->where('question', 'like', $term)->orWhere('answer', 'like', $term);
            });
        }

        $items = $query->limit(1000)->get();

        return response()->json([
            'success' => true,
            'message' => 'Support FAQs retrieved successfully.',
            'data'    => $items->map(fn (HubSupportFaq $f) => $this->serializeAdmin($f))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedPayload($request);

        $faq = new HubSupportFaq($data);
        $faq->created_by = $request->user()?->id;
        $faq->save();

        return response()->json([
            'success' => true,
            'message' => 'FAQ created successfully.',
            'data'    => $this->serializeAdmin($faq->fresh()),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $hubSupportFaq = HubSupportFaq::query()->findOrFail($id);

        $data = $this->validatedPayload($request, isUpdate: true);
        $hubSupportFaq->fill($data);
        $hubSupportFaq->save();

        return response()->json([
            'success' => true,
            'message' => 'FAQ updated successfully.',
            'data'    => $this->serializeAdmin($hubSupportFaq->fresh()),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $hubSupportFaq = HubSupportFaq::query()->findOrFail($id);
        $hubSupportFaq->delete();

        return response()->json([
            'success' => true,
            'message' => 'FAQ archived successfully.',
            'data'    => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'question'      => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:500'],
            'answer'        => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:100000'],
            'sort_order'    => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_published'  => ['nullable', 'boolean'],
        ];

        $validated = $request->validate($rules);

        if (array_key_exists('is_published', $validated)) {
            $validated['is_published'] = (bool) $validated['is_published'];
        }

        if (array_key_exists('sort_order', $validated)) {
            $validated['sort_order'] = (int) $validated['sort_order'];
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAdmin(HubSupportFaq $f): array
    {
        return [
            'id'             => $f->id,
            'uuid'           => $f->uuid,
            'question'       => $f->question,
            'answer'         => $f->answer,
            'sort_order'     => $f->sort_order,
            'is_published'   => $f->is_published,
            'created_by'     => $f->created_by,
            'created_at'     => $f->created_at?->toIso8601String(),
            'updated_at'     => $f->updated_at?->toIso8601String(),
            'deleted_at'     => $f->deleted_at?->toIso8601String(),
        ];
    }
}
