<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserPushTokenRequest;
use App\Http\Resources\UserNotificationResource;
use App\Models\UserNotification;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->paginate((int) $request->get('per_page', 20));

        return $this->success([
            'notifications' => collect($notifications->items())
                ->map(fn ($item) => (new UserNotificationResource($item))->resolve())
                ->values(),
            'pagination' => $this->paginationMeta($notifications),
            'unread_count' => $request->user()->notifications()->whereNull('read_at')->count(),
        ], 'Notifications loaded');
    }

    public function show(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        return $this->success([
            'notification' => (new UserNotificationResource($notification))->resolve(),
        ], 'Notification loaded');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success([
            'unread_count' => $request->user()->notifications()->whereNull('read_at')->count(),
        ], 'Unread notifications count');
    }

    public function markRead(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        $notification->update([
            'read_at' => $notification->read_at ?: now(),
        ]);

        if ($notification->campaign) {
            $notification->campaign->update([
                'read_count' => $notification->campaign->notifications()->whereNotNull('read_at')->count(),
            ]);
        }

        return $this->success([
            'notification' => (new UserNotificationResource($notification->fresh()))->resolve(),
        ], 'Notification marked as read');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $campaignIds = $request->user()->notifications()
            ->whereNull('read_at')
            ->whereNotNull('campaign_id')
            ->pluck('campaign_id')
            ->unique()
            ->values();

        $request->user()->notifications()->whereNull('read_at')->update([
            'read_at' => now(),
        ]);

        if ($campaignIds->isNotEmpty()) {
            \App\Models\NotificationCampaign::query()
                ->whereIn('id', $campaignIds)
                ->get()
                ->each(fn ($campaign) => $campaign->update([
                    'read_count' => $campaign->notifications()->whereNotNull('read_at')->count(),
                ]));
        }

        return $this->success([
            'unread_count' => 0,
        ], 'All notifications marked as read');
    }

    public function registerPushToken(UserPushTokenRequest $request): JsonResponse
    {
        $token = $request->user()->pushTokens()->updateOrCreate(
            ['token' => $request->string('token')->toString()],
            [
                'platform' => $request->string('platform')->toString(),
                'device_name' => $request->filled('device_name') ? $request->string('device_name')->toString() : null,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return $this->success([
            'push_token' => [
                'id' => $token->id,
                'platform' => $token->platform,
                'device_name' => $token->device_name,
                'is_active' => $token->is_active,
                'last_used_at' => $token->last_used_at,
            ],
        ], 'Push token registered', 201);
    }

    public function unregisterPushToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $request->user()->pushTokens()
            ->where('token', $validated['token'])
            ->update([
                'is_active' => false,
            ]);

        return $this->success(null, 'Push token deactivated');
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
