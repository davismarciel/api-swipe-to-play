<?php

namespace Modules\User\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferredCategoriesRequest extends FormRequest
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
            'categories' => 'required|array',
            'categories.*.category_id' => 'required_with:categories.*|exists:categories,id',
            'categories.*.preference_weight' => 'required_with:categories.*|integer|min:1|max:10',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'categories.required' => 'The categories field is required.',
            'categories.array' => 'The categories field must be an array.',
            'categories.*.category_id.required' => 'Each category must have a category_id.',
            'categories.*.category_id.exists' => 'One or more category IDs do not exist.',
            'categories.*.preference_weight.required' => 'Each category must have a preference_weight.',
            'categories.*.preference_weight.integer' => 'The preference_weight must be an integer.',
            'categories.*.preference_weight.min' => 'The preference_weight must be at least 1.',
            'categories.*.preference_weight.max' => 'The preference_weight must not exceed 10.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $categories = $this->input('categories', []);
        
        if (empty($categories)) {
            $this->merge(['categories' => []]);
        }
    }
}

