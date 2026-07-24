<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.image' => 'The uploaded file is not a supported image.',
            'photo.mimes' => 'Only JPG, PNG, and WebP images are supported.',
            'photo.max' => 'Images may not be larger than 20 MB.',
        ];
    }
}
