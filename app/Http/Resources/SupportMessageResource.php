<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'support_case_id' => $this->support_case_id,
            'sender_type' => $this->sender_type,
            'message' => $this->message,
            'attachments' => $this->attachments ?? [],
            'is_internal' => (bool) $this->is_internal,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'role' => $this->user?->role,
            ]),
        ];
    }
}
