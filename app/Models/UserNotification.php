<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    use HasUuids;

    protected $fillable = [
        'campaign_id',
        'user_id',
        'type',
        'title',
        'body',
        'cta_label',
        'cta_url',
        'channels',
        'channel_statuses',
        'data',
        'delivered_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'channel_statuses' => 'array',
            'data' => 'array',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NotificationCampaign::class, 'campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
