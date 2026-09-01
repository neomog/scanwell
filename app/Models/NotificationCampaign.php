<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationCampaign extends Model
{
    use HasUuids;

    public const TYPE_ANNOUNCEMENT = 'announcement';
    public const TYPE_CAMPAIGN = 'campaign';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_PARTIALLY_SENT = 'partially_sent';
    public const STATUS_FAILED = 'failed';

    public const AUDIENCE_ALL_USERS = 'all_users';
    public const AUDIENCE_ROLES = 'roles';
    public const AUDIENCE_USERS = 'users';

    protected $fillable = [
        'created_by',
        'type',
        'status',
        'title',
        'subject',
        'body',
        'cta_label',
        'cta_url',
        'audience_type',
        'audience_filters',
        'channels',
        'recipients_count',
        'read_count',
        'delivery_summary',
        'scheduled_at',
        'sent_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'audience_filters' => 'array',
            'channels' => 'array',
            'delivery_summary' => 'array',
            'meta' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class, 'campaign_id');
    }
}
