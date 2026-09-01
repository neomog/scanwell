<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportCase extends Model
{
    use HasUuids;

    public const TYPE_COMPLAINT = 'complaint';
    public const TYPE_TICKET = 'ticket';
    public const TYPE_BUG_REPORT = 'bug_report';
    public const TYPE_CHAT_SUPPORT = 'chat_support';

    public const STATUS_OPEN = 'open';
    public const STATUS_PENDING_SUPPORT = 'pending_support';
    public const STATUS_PENDING_USER = 'pending_user';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'reference',
        'user_id',
        'assigned_to',
        'type',
        'subject',
        'description',
        'status',
        'priority',
        'source',
        'attachments',
        'metadata',
        'customer_last_read_at',
        'support_last_read_at',
        'last_message_at',
        'resolved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'metadata' => 'array',
            'customer_last_read_at' => 'datetime',
            'support_last_read_at' => 'datetime',
            'last_message_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('created_at');
    }
}
