<?php

namespace Modules\User\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserMonetizationPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'tolerance_microtransactions' => $this->tolerance_microtransactions,
            'tolerance_dlc' => $this->tolerance_dlc,
            'tolerance_season_pass' => $this->tolerance_season_pass,
            'tolerance_loot_boxes' => $this->tolerance_loot_boxes,
            'tolerance_battle_pass' => $this->tolerance_battle_pass,
            'tolerance_ads' => $this->tolerance_ads,
            'tolerance_pay_to_win' => $this->tolerance_pay_to_win,
            'prefer_cosmetic_only' => $this->prefer_cosmetic_only,
            'avoid_subscription' => $this->avoid_subscription,
            'prefer_one_time_purchase' => $this->prefer_one_time_purchase,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
