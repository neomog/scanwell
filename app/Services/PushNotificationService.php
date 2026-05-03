<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;

class PushNotificationService
{
    public function send(User $user, array $payload): array
    {
        $tokens = $user->pushTokens()
            ->where('is_active', true)
            ->get();

        if ($tokens->isEmpty()) {
            return [
                'status' => 'skipped',
                'message' => 'No active push tokens registered.',
                'tokens' => 0,
            ];
        }

        if (! config('notifications.push.enabled') || ! config('notifications.push.endpoint') || ! config('notifications.push.token')) {
            return [
                'status' => 'skipped',
                'message' => 'Push delivery is not configured.',
                'tokens' => $tokens->count(),
            ];
        }

        $response = Http::timeout((int) config('notifications.push.timeout', 10))
            ->withToken((string) config('notifications.push.token'))
            ->post((string) config('notifications.push.endpoint'), [
                'user_id' => $user->id,
                'tokens' => $tokens->pluck('token')->values()->all(),
                'notification' => $payload,
            ]);

        if ($response->failed()) {
            return [
                'status' => 'failed',
                'message' => $response->json('message') ?? $response->body(),
                'tokens' => $tokens->count(),
            ];
        }

        $user->pushTokens()->whereIn('id', $tokens->pluck('id'))->update([
            'last_used_at' => now(),
        ]);

        return [
            'status' => 'sent',
            'message' => 'Push sent successfully.',
            'tokens' => $tokens->count(),
            'response' => $response->json(),
        ];
    }
}
