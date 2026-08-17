<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'subscription' => [
                            'plan' => ['slug', 'name'],
                            'status',
                        ],
                    ],
                    'token',
                    'token_type',
                ],
            ])
            ->assertJsonPath('data.user.subscription.plan.slug', 'free');
    }

    public function test_apple_user_can_delete_account_with_email_confirmation(): void
    {
        $user = User::factory()->create([
            'provider' => 'apple',
            'provider_id' => 'apple-user-123',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/account', [
            'confirmation' => 'DELETE',
            'email' => $user->email,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_user_can_authenticate_with_a_valid_apple_identity_token(): void
    {
        $keyOptions = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $windowsOpenSslConfig = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf';

        if (PHP_OS_FAMILY === 'Windows' && is_file($windowsOpenSslConfig)) {
            $keyOptions['config'] = $windowsOpenSslConfig;
        }

        $key = openssl_pkey_new($keyOptions);
        openssl_pkey_export($key, $privateKey, null, $keyOptions);
        $details = openssl_pkey_get_details($key);

        $encode = static fn (string $value): string => rtrim(
            strtr(base64_encode($value), '+/', '-_'),
            '='
        );

        Http::fake([
            'https://appleid.apple.com/auth/keys' => Http::response([
                'keys' => [[
                    'kty' => 'RSA',
                    'kid' => 'test-apple-key',
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'n' => $encode($details['rsa']['n']),
                    'e' => $encode($details['rsa']['e']),
                ]],
            ]),
        ]);

        Cache::forget('apple-sign-in-public-keys');
        config(['services.apple.client_id' => 'com.infinistream.labelwisemobileapp']);

        $identityToken = JWT::encode([
            'iss' => 'https://appleid.apple.com',
            'aud' => 'com.infinistream.labelwisemobileapp',
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(5)->timestamp,
            'sub' => 'apple-auth-user-123',
            'email' => 'apple-user@example.com',
        ], $privateKey, 'RS256', 'test-apple-key');

        $this->postJson('/api/auth/apple', [
            'identity_token' => $identityToken,
            'authorization_code' => 'test-authorization-code',
            'name' => 'Apple User',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'apple-user@example.com')
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertDatabaseHas('users', [
            'email' => 'apple-user@example.com',
            'provider' => 'apple',
            'provider_id' => 'apple-auth-user-123',
        ]);
    }

    public function test_apple_user_must_confirm_matching_email_to_delete_account(): void
    {
        $user = User::factory()->create([
            'provider' => 'apple',
            'provider_id' => 'apple-user-456',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/account', [
            'confirmation' => 'DELETE',
            'email' => 'someone-else@example.com',
        ])->assertUnprocessable();

        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
    }
}
