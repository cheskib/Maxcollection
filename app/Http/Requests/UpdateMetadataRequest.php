<?php

namespace App\Http\Requests;

use App\Models\Metadata;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMetadataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'category' => ['required', Rule::in(['sports_card', 'comic_book', 'coin', 'stamp', 'unsupported'])],
            'condition_notes' => ['nullable', 'string', 'max:5000'],
            'value_from' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'value_to' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'insurance_value' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];

        foreach (array_keys(Metadata::FIELD_LABELS) as $field) {
            $rules[$field] ??= ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }
}
