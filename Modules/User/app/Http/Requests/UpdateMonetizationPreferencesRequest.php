<?php

namespace Modules\User\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMonetizationPreferencesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tolerance_microtransactions' => 'sometimes|integer|min:0|max:10',
            'tolerance_dlc' => 'sometimes|integer|min:0|max:10',
            'tolerance_season_pass' => 'sometimes|integer|min:0|max:10',
            'tolerance_loot_boxes' => 'sometimes|integer|min:0|max:10',
            'tolerance_battle_pass' => 'sometimes|integer|min:0|max:10',
            'tolerance_ads' => 'sometimes|integer|min:0|max:10',
            'tolerance_pay_to_win' => 'sometimes|integer|min:0|max:10',
            'prefer_cosmetic_only' => 'sometimes|boolean',
            'avoid_subscription' => 'sometimes|boolean',
            'prefer_one_time_purchase' => 'sometimes|boolean',
        ];
    }
}

