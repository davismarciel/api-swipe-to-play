<?php

namespace Modules\Game\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunityRatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (!$this->resource) {
            return [];
        }

        return [
            'toxicity' => $this->toxicity_rate,
            'bugs' => $this->bug_rate,
            'microtransactions' => $this->microtransaction_rate,
            'optimization' => $this->bad_optimization_rate,
            'cheaters' => $this->cheater_rate,
        ];
    }
}

