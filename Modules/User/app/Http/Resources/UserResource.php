<?php

namespace Modules\User\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'google_id' => $this->google_id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'provider' => $this->provider,
            'email_verified_at' => $this->email_verified_at,
            'profile' => $this->whenLoaded('profile'),
            'preferences' => $this->whenLoaded('preferences'),
            'monetization_preferences' => $this->whenLoaded('monetizationPreferences'),
            'preferred_genres' => $this->whenLoaded('preferredGenres'),
            'preferred_categories' => $this->whenLoaded('preferredCategories'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
