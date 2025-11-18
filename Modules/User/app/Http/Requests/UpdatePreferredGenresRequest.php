<?php

namespace Modules\User\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferredGenresRequest extends FormRequest
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
            'genres' => 'required|array',
            'genres.*.genre_id' => 'required_with:genres.*|exists:genres,id',
            'genres.*.preference_weight' => 'required_with:genres.*|integer|min:1|max:10',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'genres.required' => 'The genres field is required.',
            'genres.array' => 'The genres field must be an array.',
            'genres.*.genre_id.required' => 'Each genre must have a genre_id.',
            'genres.*.genre_id.exists' => 'One or more genre IDs do not exist.',
            'genres.*.preference_weight.required' => 'Each genre must have a preference_weight.',
            'genres.*.preference_weight.integer' => 'The preference_weight must be an integer.',
            'genres.*.preference_weight.min' => 'The preference_weight must be at least 1.',
            'genres.*.preference_weight.max' => 'The preference_weight must not exceed 10.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $genres = $this->input('genres', []);
        
        if (empty($genres)) {
            $this->merge(['genres' => []]);
        }
    }
}

