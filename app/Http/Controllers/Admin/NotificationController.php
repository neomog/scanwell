<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotificationCampaignRequest;
use App\Models\NotificationCampaign;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationAudienceResolver;
use App\Services\NotificationDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationDeliveryService $notificationDeliveryService,
        protected NotificationAudienceResolver $notificationAudienceResolver
    ) {
    }

    public function index(Request $request): View
    {
        $query = NotificationCampaign::query()->with('creator')->latest();

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        return view('admin.notifications.index', [
            'campaigns' => $query->paginate(15)->withQueryString(),
            'types' => config('notifications.types', []),
            'statuses' => [
                NotificationCampaign::STATUS_DRAFT,
                NotificationCampaign::STATUS_SCHEDULED,
                NotificationCampaign::STATUS_PROCESSING,
                NotificationCampaign::STATUS_SENT,
                NotificationCampaign::STATUS_PARTIALLY_SENT,
                NotificationCampaign::STATUS_FAILED,
            ],
            'stats' => [
                'total' => NotificationCampaign::count(),
                'scheduled' => NotificationCampaign::where('status', NotificationCampaign::STATUS_SCHEDULED)->count(),
                'sent_today' => NotificationCampaign::whereDate('sent_at', today())->count(),
                'announcements' => NotificationCampaign::where('type', NotificationCampaign::TYPE_ANNOUNCEMENT)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.notifications.create', [
            'types' => config('notifications.types', []),
            'channels' => config('notifications.channels', []),
            'audiences' => config('notifications.audiences', []),
            'roles' => Role::query()->orderBy('name')->get(),
            'users' => User::query()->orderBy('name')->limit(100)->get(['id', 'name', 'email']),
        ]);
    }

    public function store(NotificationCampaignRequest $request): RedirectResponse
    {
        $campaign = $this->notificationDeliveryService->createCampaign($request->campaignData(), $request->user());

        return redirect()
            ->route('admin.notifications.show', $campaign)
            ->with('success', $campaign->status === NotificationCampaign::STATUS_SCHEDULED
                ? 'Notification scheduled successfully.'
                : 'Notification created and dispatched successfully.');
    }

    public function show(NotificationCampaign $notification): View
    {
        $notification->load(['creator']);
        $this->notificationDeliveryService->refreshReadCount($notification);

        return view('admin.notifications.show', [
            'campaign' => $notification->fresh(['creator']),
            'recipients' => $notification->notifications()
                ->with('user')
                ->latest()
                ->paginate(20),
            'audienceCount' => $this->notificationAudienceResolver->count($notification),
        ]);
    }

    public function send(NotificationCampaign $notification): RedirectResponse
    {
        $notification = $this->notificationDeliveryService->dispatch($notification, true);

        return redirect()
            ->route('admin.notifications.show', $notification)
            ->with('success', 'Notification dispatched successfully.');
    }
}
