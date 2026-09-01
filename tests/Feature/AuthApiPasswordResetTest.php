<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ApiResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthApiPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_mobile_reset_notification(): void
    {
        Notification::fake();

        config()->set('app.mobile_reset_password_url', 'scanwell://reset-password');

        $user = User::factory()->create([
            'email' => 'mobile-reset@example.com',
        ]);

        $response = $this->postJson('/api/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'errors' => null,
            ]);

        Notification::assertSentTo($user, ApiResetPasswordNotification::class, function ($notification, $channels) use ($user) {
            $url = $notification->buildResetUrl($user);

            $this->assertContains('mail', $channels);
            $this->assertStringStartsWith('scanwell://reset-password?', $url);
            $this->assertStringContainsString('token=', $url);
            $this->assertStringContainsString('email=mobile-reset%40example.com', $url);

            return true;
        });
    }

    public function test_password_can_be_reset_via_api_and_existing_tokens_are_revoked(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password-123'),
        ]);

        $user->createToken('device-token');

        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'errors' => null,
            ]);

        $user->refresh();

        $this->assertTrue(Hash::check('new-password-123', $user->password));
        $this->assertFalse(Hash::check('old-password-123', $user->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_reset_password_requires_a_valid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'data' => null,
                'errors' => null,
            ]);
    }
}
