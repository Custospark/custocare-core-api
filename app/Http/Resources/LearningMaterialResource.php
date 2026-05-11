<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\LearningMaterial
 */
class LearningMaterialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'uuid'             => $this->uuid,
            'title'            => $this->title,
            'description'      => $this->description,
            'video_url'        => $this->video_url,
            'thumbnail_url'    => $this->thumbnail_url,
            'banner_image_url' => $this->banner_image_url,
            'category'         => $this->category,
            'sort_order'       => $this->sort_order,
            'is_published'     => $this->is_published,
            'created_by'       => $this->created_by,
            'created_at'       => $this->created_at?->toIso8601String(),
            'updated_at'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
