<?php

namespace App\Services;

use App\Mail\NotificationCampaignMail;
use App\Models\NotificationCampaign;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationDeliveryService
{
    public function __construct(
        protected NotificationAudienceResolver $audienceResolver,
        protected PushNotificationService $pushNotificationService
    ) {
    }

    public function createCampaign(array $data, User $actor): NotificationCampaign
    {
        $campaign = NotificationCampaign::create(array_merge($data, [
            'created_by' => $actor->id,
            'status' => $this->resolveInitialStatus($data),
        ]));

        if ($campaign->status !== NotificationCampaign::STATUS_SCHEDULED) {
            $this->dispatch($campaign);
        }

        return $campaign->fresh(['creator']);
    }

    public function dispatch(NotificationCampaign $campaign, bool $force = false): NotificationCampaign
    {
        if ($campaign->scheduled_at && $campaign->scheduled_at->isFuture() && ! $force) {
            $campaign->update(['status' => NotificationCampaign::STATUS_SCHEDULED]);

            return $campaign;
        }

        $campaign->update([
            'status' => NotificationCampaign::STATUS_PROCESSING,
        ]);

        $users = $this->audienceResolver->resolve($campaign);
        $summary = [
            'in_app' => ['sent' => 0, 'failed' => 0],
            'email' => ['sent' => 0, 'failed' => 0, 'skipped' => 0],
            'push' => ['sent' => 0, 'failed' => 0, 'skipped' => 0],
        ];

        foreach ($users as $user) {
            $channelStatuses = [
                'in_app' => ['status' => 'sent', 'sent_at' => now()->toDateTimeString()],
            ];

            if (in_array('email', $campaign->channels ?? [], true)) {
                $channelStatuses['email'] = $this->sendEmail($campaign, $user);
                $summary['email'][$channelStatuses['email']['status']] = ($summary['email'][$channelStatuses['email']['status']] ?? 0) + 1;
            }

            if (in_array('push', $campaign->channels ?? [], true)) {
                $pushResult = $this->pushNotificationService->send($user, [
                    'title' => $campaign->title,
                    'body' => $campaign->body,
                    'cta_url' => $campaign->cta_url,
                    'cta_label' => $campaign->cta_label,
                    'type' => $campaign->type,
                    'campaign_id' => $campaign->id,
                ]);

                $channelStatuses['push'] = [
                    'status' => $pushResult['status'],
                    'message' => $pushResult['message'] ?? null,
                    'sent_at' => now()->toDateTimeString(),
                    'tokens' => $pushResult['tokens'] ?? 0,
                ];
                $summary['push'][$pushResult['status']] = ($summary['push'][$pushResult['status']] ?? 0) + 1;
            }

            DB::transaction(function () use ($campaign, $user, $channelStatuses, &$summary) {
                UserNotification::updateOrCreate(
                    [
                        'campaign_id' => $campaign->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'type' => $campaign->type,
                        'title' => $campaign->title,
                        'body' => $campaign->body,
                        'cta_label' => $campaign->cta_label,
                        'cta_url' => $campaign->cta_url,
                        'channels' => $campaign->channels,
                        'channel_statuses' => $channelStatuses,
                        'data' => [
                            'campaign_status' => $campaign->status,
                        ],
                        'delivered_at' => now(),
                        'read_at' => null,
                    ]
                );

                $summary['in_app']['sent']++;
            });
        }

        $failed = ($summary['email']['failed'] ?? 0) + ($summary['push']['failed'] ?? 0);
        $status = $users->isEmpty()
            ? NotificationCampaign::STATUS_FAILED
            : ($failed > 0 ? NotificationCampaign::STATUS_PARTIALLY_SENT : NotificationCampaign::STATUS_SENT);

        $campaign->update([
            'status' => $status,
            'sent_at' => now(),
            'recipients_count' => $users->count(),
            'read_count' => $campaign->notifications()->whereNotNull('read_at')->count(),
            'delivery_summary' => $summary,
        ]);

        return $campaign->fresh(['creator']);
    }

    public function refreshReadCount(NotificationCampaign $campaign): void
    {
        $campaign->update([
            'read_count' => $campaign->notifications()->whereNotNull('read_at')->count(),
        ]);
    }

    protected function resolveInitialStatus(array $data): string
    {
        if (! empty($data['scheduled_at']) && $data['scheduled_at']->isFuture()) {
            return NotificationCampaign::STATUS_SCHEDULED;
        }

        return NotificationCampaign::STATUS_PROCESSING;
    }

    protected function sendEmail(NotificationCampaign $campaign, User $user): array
    {
        if (! $user->email) {
            return [
                'status' => 'skipped',
                'message' => 'User has no email address.',
            ];
        }

        try {
            Mail::to($user->email)->send(new NotificationCampaignMail($campaign, $user));

            return [
                'status' => 'sent',
                'message' => 'Email sent successfully.',
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ];
        }
    }
}
