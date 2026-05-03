<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupportMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['string', 'max:1000'],
            'is_internal' => ['nullable', 'boolean'],
        ];
    }
}
