<?php

namespace App\Http\Resources;

use App\Services\SubscriptionManager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
//    public function toArray(Request $request): array
//    {
//        return parent::toArray($request);
//    }
    public function toArray($request)
    {
        $currentSubscription = app(SubscriptionManager::class)
            ->currentSubscription($this->resource)
            ->loadMissing(['plan', 'price']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'avatar' => $this->avatar,
            'provider' => $this->provider,
            'role' => $this->role,
            'capabilities' => [
                'can_review_submissions' => $this->hasPermission('submissions.view'),
                'can_moderate_submissions' => $this->hasPermission('submissions.approve'),
            ],
            'subscription' => [
                'plan' => [
                    'id' => $currentSubscription->plan?->id,
                    'slug' => $currentSubscription->plan?->slug,
                    'name' => $currentSubscription->plan?->name,
                ],
                'price' => [
                    'id' => $currentSubscription->price?->id,
                    'name' => $currentSubscription->price?->name,
                ],
                'status' => $currentSubscription->status,
                'ends_at' => $currentSubscription->ends_at,
                'current_period_ends_at' => $currentSubscription->current_period_ends_at,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
