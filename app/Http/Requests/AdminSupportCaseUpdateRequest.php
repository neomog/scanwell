<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminSupportCaseUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(array_keys(config('support.statuses', [])))],
            'priority' => ['nullable', Rule::in(array_keys(config('support.priorities', [])))],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ];
    }
}
