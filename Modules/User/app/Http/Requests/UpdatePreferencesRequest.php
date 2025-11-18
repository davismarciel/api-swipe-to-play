<?php

namespace Modules\User\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
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
            'prefer_windows' => 'sometimes|boolean',
            'prefer_mac' => 'sometimes|boolean',
            'prefer_linux' => 'sometimes|boolean',
            'preferred_languages' => 'sometimes|array',
            'prefer_single_player' => 'sometimes|boolean',
            'prefer_multiplayer' => 'sometimes|boolean',
            'prefer_coop' => 'sometimes|boolean',
            'prefer_competitive' => 'sometimes|boolean',
            'min_age_rating' => 'sometimes|integer|min:0|max:18',
            'avoid_violence' => 'sometimes|boolean',
            'avoid_nudity' => 'sometimes|boolean',
            'max_price' => 'sometimes|numeric|min:0',
            'prefer_free_to_play' => 'sometimes|boolean',
            'include_early_access' => 'sometimes|boolean',
        ];
    }
}

