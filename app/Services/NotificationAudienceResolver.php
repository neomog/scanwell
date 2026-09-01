<?php

namespace App\Services;

use App\Models\NotificationCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class NotificationAudienceResolver
{
    public function resolve(NotificationCampaign $campaign): Collection
    {
        return $this->query($campaign)->get();
    }

    public function count(NotificationCampaign $campaign): int
    {
        return $this->query($campaign)->count();
    }

    protected function query(NotificationCampaign $campaign): Builder
    {
        $query = User::query()
            ->whereNull('deleted_at')
            ->orderBy('name');

        $filters = $campaign->audience_filters ?? [];

        return match ($campaign->audience_type) {
            NotificationCampaign::AUDIENCE_ROLES => $query->whereIn('role', collect($filters['roles'] ?? [])->filter()->values()),
            NotificationCampaign::AUDIENCE_USERS => $query->whereIn('id', collect($filters['user_ids'] ?? [])->filter()->values()),
            default => $query,
        };
    }
}
