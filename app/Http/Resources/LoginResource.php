<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this['user']),
            'token' => $this['token'],
            'requires_mfa' => $this['requires_mfa'],
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration') ?: null,
        ];
    }
}