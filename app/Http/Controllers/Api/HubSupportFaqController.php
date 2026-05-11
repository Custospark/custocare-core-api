<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HubSupportFaq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authenticated users: read published Support Center FAQs (Custocare Hub).
 */
class HubSupportFaqController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        $query = HubSupportFaq::query()
            ->published()
            ->orderBy('sort_order')
            ->orderBy('id');

        if (! empty($validated['q'])) {
            $term = '%'.$validated['q'].'%';
            $query->where(function ($q) use ($term): void {
                $q->where('question', 'like', $term)->orWhere('answer', 'like', $term);
            });
        }

        $items = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'FAQs retrieved successfully.',
            'data'    => $items->map(fn (HubSupportFaq $f) => $this->serializePublic($f))->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePublic(HubSupportFaq $f): array
    {
        return [
            'uuid'        => $f->uuid,
            'question'    => $f->question,
            'answer'      => $f->answer,
            'sort_order'  => $f->sort_order,
            'updated_at'  => $f->updated_at?->toIso8601String(),
        ];
    }
}
