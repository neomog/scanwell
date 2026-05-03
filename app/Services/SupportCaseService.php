<?php

namespace App\Services;

use App\Models\SupportCase;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SupportCaseService
{
    public function createForUser(User $user, array $payload): SupportCase
    {
        $now = now();

        $case = SupportCase::create([
            'reference' => $this->generateReference(),
            'user_id' => $user->id,
            'type' => $payload['type'],
            'subject' => $payload['subject'],
            'description' => $payload['description'],
            'status' => SupportCase::STATUS_PENDING_SUPPORT,
            'priority' => $payload['priority'] ?? 'normal',
            'source' => $payload['source'] ?? 'mobile',
            'attachments' => $payload['attachments'] ?? [],
            'metadata' => $payload['metadata'] ?? [],
            'customer_last_read_at' => $now,
            'last_message_at' => $now,
        ]);

        $this->addMessage($case, $user, [
            'message' => $payload['description'],
            'attachments' => $payload['attachments'] ?? [],
            'is_internal' => false,
        ], 'customer');

        return $case->fresh(['user', 'assignee']);
    }

    public function addMessage(SupportCase $case, ?User $actor, array $payload, string $senderType): SupportMessage
    {
        $message = $case->messages()->create([
            'user_id' => $actor?->id,
            'sender_type' => $senderType,
            'message' => $payload['message'],
            'attachments' => $payload['attachments'] ?? [],
            'is_internal' => (bool) ($payload['is_internal'] ?? false),
        ]);

        $updates = [
            'last_message_at' => $message->created_at,
        ];

        if ($senderType === 'customer') {
            $updates['status'] = SupportCase::STATUS_PENDING_SUPPORT;
            $updates['customer_last_read_at'] = $message->created_at;
        }

        if ($senderType === 'support') {
            $updates['status'] = $message->is_internal ? $case->status : SupportCase::STATUS_PENDING_USER;
            $updates['support_last_read_at'] = $message->created_at;
            if (! $case->assigned_to && $actor) {
                $updates['assigned_to'] = $actor->id;
            }
        }

        if ($senderType === 'system') {
            $updates['support_last_read_at'] = $message->created_at;
        }

        $case->update($updates);

        return $message->fresh('user');
    }

    public function updateCase(SupportCase $case, array $payload): SupportCase
    {
        $updates = Arr::only($payload, ['status', 'priority', 'assigned_to']);

        if (($updates['status'] ?? null) === SupportCase::STATUS_RESOLVED) {
            $updates['resolved_at'] = now();
        }

        if (($updates['status'] ?? null) === SupportCase::STATUS_CLOSED) {
            $updates['closed_at'] = now();
        }

        if (($updates['status'] ?? null) && ! in_array($updates['status'], [SupportCase::STATUS_RESOLVED, SupportCase::STATUS_CLOSED], true)) {
            $updates['resolved_at'] = null;
            $updates['closed_at'] = null;
        }

        $case->update($updates);

        return $case->fresh(['user', 'assignee']);
    }

    public function markViewedByCustomer(SupportCase $case): void
    {
        $case->update([
            'customer_last_read_at' => now(),
        ]);
    }

    public function markViewedBySupport(SupportCase $case): void
    {
        $case->update([
            'support_last_read_at' => now(),
        ]);
    }

    protected function generateReference(): string
    {
        return 'SUP-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
    }
}
