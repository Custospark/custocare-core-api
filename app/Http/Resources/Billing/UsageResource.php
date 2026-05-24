<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'staff'       => (int) ($this['staff'] ?? 0),
            'departments' => (int) ($this['departments'] ?? 0),
            'visits'      => (int) ($this['visits'] ?? 0),
            'limits'      => $this['limits'] ?? null,
        ];
    }
}
