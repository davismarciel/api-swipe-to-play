<?php

namespace Modules\Auth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->resource['access_token'] ?? null,
            'token_type' => $this->resource['token_type'] ?? 'bearer',
            'expires_in' => $this->resource['expires_in'] ?? null,
            'user' => $this->resource['user'] ?? null,
        ];
    }
}
