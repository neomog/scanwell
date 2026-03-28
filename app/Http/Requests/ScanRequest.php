<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Or check if user is authenticated
    }

    public function rules(): array
    {
        return [
            'barcode' => ['required', 'string', 'max:50', 'regex:/^[0-9]{8,13}$/'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'barcode.regex' => 'Invalid barcode format. Must be 8-13 digits.',
        ];
    }
}
