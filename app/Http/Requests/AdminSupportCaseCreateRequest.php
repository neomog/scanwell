<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminSupportCaseCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'type' => ['required', Rule::in(array_keys(config('support.types', [])))],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(array_keys(config('support.statuses', [])))],
            'priority' => ['nullable', Rule::in(array_keys(config('support.priorities', [])))],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['string', 'max:1000'],
        ];
    }
}
