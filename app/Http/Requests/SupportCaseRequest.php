<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupportCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(config('support.types', [])))],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::in(array_keys(config('support.priorities', [])))],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['string', 'max:1000'],
            'metadata' => ['nullable', 'array'],
            'metadata.platform' => ['nullable', 'string', 'max:50'],
            'metadata.app_version' => ['nullable', 'string', 'max:100'],
            'metadata.os_version' => ['nullable', 'string', 'max:100'],
            'metadata.device_name' => ['nullable', 'string', 'max:255'],
            'metadata.screen' => ['nullable', 'string', 'max:255'],
        ];
    }
}
