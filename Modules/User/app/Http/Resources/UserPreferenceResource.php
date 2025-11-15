<?php

namespace Modules\User\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'prefer_windows' => $this->prefer_windows,
            'prefer_mac' => $this->prefer_mac,
            'prefer_linux' => $this->prefer_linux,
            'preferred_languages' => $this->preferred_languages,
            'prefer_single_player' => $this->prefer_single_player,
            'prefer_multiplayer' => $this->prefer_multiplayer,
            'prefer_coop' => $this->prefer_coop,
            'prefer_competitive' => $this->prefer_competitive,
            'min_age_rating' => $this->min_age_rating,
            'avoid_violence' => $this->avoid_violence,
            'avoid_nudity' => $this->avoid_nudity,
            'include_early_access' => $this->include_early_access,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
