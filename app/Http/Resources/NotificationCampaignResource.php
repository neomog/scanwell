<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationCampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'title' => $this->title,
            'subject' => $this->subject,
            'body' => $this->body,
            'cta_label' => $this->cta_label,
            'cta_url' => $this->cta_url,
            'audience_type' => $this->audience_type,
            'audience_filters' => $this->audience_filters ?? [],
            'channels' => $this->channels ?? [],
            'recipients_count' => (int) $this->recipients_count,
            'read_count' => (int) $this->read_count,
            'delivery_summary' => $this->delivery_summary ?? [],
            'scheduled_at' => $this->scheduled_at,
            'sent_at' => $this->sent_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
                'email' => $this->creator?->email,
            ]),
        ];
    }
}
