<?php

namespace App\Http\Requests;

use App\Models\NotificationCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(config('notifications.types', [])))],
            'title' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'cta_label' => ['nullable', 'string', 'max:100'],
            'cta_url' => ['nullable', 'url', 'max:500'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['string', Rule::in(array_keys(config('notifications.channels', [])))],
            'audience_type' => ['required', Rule::in(array_keys(config('notifications.audiences', [])))],
            'audience.role_slugs' => ['nullable', 'array'],
            'audience.role_slugs.*' => ['string', 'exists:roles,slug'],
            'audience.user_ids' => ['nullable', 'array'],
            'audience.user_ids.*' => ['string', 'exists:users,id'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('audience_type') === NotificationCampaign::AUDIENCE_ROLES
                && blank($this->input('audience.role_slugs', []))) {
                $validator->errors()->add('audience.role_slugs', 'Select at least one role for role-based targeting.');
            }

            if ($this->input('audience_type') === NotificationCampaign::AUDIENCE_USERS
                && blank($this->input('audience.user_ids', []))) {
                $validator->errors()->add('audience.user_ids', 'Select at least one user for user-based targeting.');
            }
        });
    }

    public function campaignData(): array
    {
        return [
            'type' => $this->string('type')->toString(),
            'title' => $this->string('title')->toString(),
            'subject' => $this->filled('subject') ? $this->string('subject')->toString() : null,
            'body' => $this->string('body')->toString(),
            'cta_label' => $this->filled('cta_label') ? $this->string('cta_label')->toString() : null,
            'cta_url' => $this->filled('cta_url') ? $this->string('cta_url')->toString() : null,
            'channels' => collect($this->input('channels', []))->filter()->values()->all(),
            'audience_type' => $this->string('audience_type')->toString(),
            'audience_filters' => [
                'roles' => collect($this->input('audience.role_slugs', []))->filter()->values()->all(),
                'user_ids' => collect($this->input('audience.user_ids', []))->filter()->values()->all(),
            ],
            'scheduled_at' => $this->date('scheduled_at'),
        ];
    }
}
