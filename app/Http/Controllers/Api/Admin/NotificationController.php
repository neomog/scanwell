<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotificationCampaignRequest;
use App\Http\Resources\NotificationCampaignResource;
use App\Models\NotificationCampaign;
use App\Services\NotificationDeliveryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected NotificationDeliveryService $notificationDeliveryService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $campaigns = NotificationCampaign::query()
            ->with('creator')
            ->latest()
            ->paginate((int) $request->get('per_page', 20));

        return $this->success([
            'notifications' => collect($campaigns->items())
                ->map(fn ($item) => (new NotificationCampaignResource($item))->resolve())
                ->values(),
            'pagination' => $this->paginationMeta($campaigns),
        ], 'Notification campaigns loaded');
    }

    public function store(NotificationCampaignRequest $request): JsonResponse
    {
        $campaign = $this->notificationDeliveryService->createCampaign($request->campaignData(), $request->user());

        return $this->success([
            'notification' => (new NotificationCampaignResource($campaign))->resolve(),
        ], $campaign->status === NotificationCampaign::STATUS_SCHEDULED
            ? 'Notification scheduled successfully'
            : 'Notification created successfully', 201);
    }

    public function show(NotificationCampaign $notification): JsonResponse
    {
        $notification->load(['creator']);
        $recipients = $notification->notifications()
            ->with('user')
            ->latest()
            ->paginate(20);

        return $this->success([
            'notification' => (new NotificationCampaignResource($notification))->resolve(),
            'recipients' => collect($recipients->items())
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'user' => [
                        'id' => $item->user?->id,
                        'name' => $item->user?->name,
                        'email' => $item->user?->email,
                    ],
                    'is_read' => filled($item->read_at),
                    'read_at' => $item->read_at,
                    'delivered_at' => $item->delivered_at,
                    'channel_statuses' => $item->channel_statuses ?? [],
                ])
                ->values(),
            'pagination' => $this->paginationMeta($recipients),
        ], 'Notification campaign loaded');
    }

    public function send(NotificationCampaign $notification): JsonResponse
    {
        $notification = $this->notificationDeliveryService->dispatch($notification, true);

        return $this->success([
            'notification' => (new NotificationCampaignResource($notification))->resolve(),
        ], 'Notification dispatched successfully');
    }

    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
