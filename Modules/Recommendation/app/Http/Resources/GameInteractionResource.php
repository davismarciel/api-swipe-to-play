<?php

namespace Modules\Recommendation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameInteractionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'game_id' => $this->game_id,
            'type' => $this->type,
            'interaction_score' => $this->interaction_score,
            'interacted_at' => $this->interacted_at,
            'game' => $this->whenLoaded('game'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
