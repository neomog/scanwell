<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImageScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'max:10240'],
            'barcode_hint' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^[0-9]{8,13}$/'],
            'product_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:255'],
            'extracted_text' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'barcode_hint.regex' => 'Invalid barcode hint format. Must be 8-13 digits.',
        ];
    }
}
