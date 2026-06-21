<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NutritionImageExtractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_extracts_nutrition_values_from_uploaded_image(): void
    {
        Sanctum::actingAs(User::factory()->create());

        config()->set('services.google_cloud_vision.credentials_json', $this->fakeGoogleCredentialsJson());

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'google-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            'https://vision.googleapis.com/v1/images:annotate' => Http::response([
                'responses' => [[
                    'fullTextAnnotation' => [
                        'text' => "Nutrition Facts\nServing Size 1 sachet (30g)\nCalories 120\nTotal Fat 8g\nSaturated Fat 2g\nTrans Fat 0g\nCholesterol 0mg\nSodium 180mg\nTotal Carbohydrate 16g\nDietary Fiber 3g\nTotal Sugars 6g\nIncludes 4g Added Sugars\nProtein 5g",
                        'pages' => [[
                            'blocks' => [
                                ['confidence' => 0.93],
                            ],
                        ]],
                    ],
                ]],
            ]),
        ]);

        $response = $this->post('/api/v1/contributions/nutrition/extract', [
            'image' => UploadedFile::fake()->image('nutrition.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nutrition.serving_size', '1 sachet (30g)')
            ->assertJsonPath('data.nutrition.calories', 120)
            ->assertJsonPath('data.nutrition.fat', 8)
            ->assertJsonPath('data.nutrition.saturated_fat', 2)
            ->assertJsonPath('data.nutrition.trans_fat', 0)
            ->assertJsonPath('data.nutrition.cholesterol', 0)
            ->assertJsonPath('data.nutrition.sodium', 180)
            ->assertJsonPath('data.nutrition.carbohydrates', 16)
            ->assertJsonPath('data.nutrition.fiber', 3)
            ->assertJsonPath('data.nutrition.sugars', 6)
            ->assertJsonPath('data.nutrition.added_sugars', 4)
            ->assertJsonPath('data.nutrition.protein', 5)
            ->assertJsonPath('data.analysis_source', 'google_cloud_vision')
            ->assertJsonPath('data.ocr_mode', 'DOCUMENT_TEXT_DETECTION');
    }

    public function test_it_returns_unprocessable_when_no_nutrition_values_can_be_extracted(): void
    {
        Sanctum::actingAs(User::factory()->create());

        config()->set('services.google_cloud_vision.credentials_json', $this->fakeGoogleCredentialsJson());

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'google-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            'https://vision.googleapis.com/v1/images:annotate' => Http::response([
                'responses' => [[
                    'fullTextAnnotation' => [
                        'text' => 'Front label only',
                        'pages' => [[
                            'blocks' => [],
                        ]],
                    ],
                ]],
            ]),
        ]);

        $response = $this->post('/api/v1/contributions/nutrition/extract', [
            'image' => UploadedFile::fake()->image('front.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    protected function fakeGoogleCredentialsJson(): string
    {
        return json_encode([
            'type' => 'service_account',
            'project_id' => 'scanwell-test',
            'private_key_id' => 'test-private-key-id',
            'private_key' => <<<KEY
-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDPU1n+v2m8dS95
QUH7yhQ3Z4EKmoU+PEOp9l6fdv4eaKQsgv7gkRze/BBIC1LFIF0GSEs9T4IkbV99
R7ZPFQqgHo0rDXhQ8i9k3r1ta7zwzZL62sN49ot9g+6UnTzSjwhZ8i6sTe2hYgeU
NuGyA9/hh3QgSF0L7SWFnqqvXxTz6+0H3qBpQ7ey6whmd4DsuLP0B2+y/MH6bQ0Y
sYvLTLbTXYRxe6fKGE4V1p5LmSO2Hehwd0hp5gXqkN1DI2G0oPLQhC4xX7Go3Xsu
3XxPPWivPjYZEkQjyl2JZP6f8GYQibY6/VX2L4m9f8rQSY5POlWHCvAvH3Q1k3T/
w4jPuscpAgMBAAECggEAFr6TvAJHe0q9fWQThg/8S6AmQ27v8C2I44b12HqfoXly
FjTl/vo7Qr2U3kghQG3ky2Mf1tAnQkWb9qGTiNtthSgxjVKMe7KBHBxKXmY68rsB
j9EtTrvO6x32g8k4y4M52Ca0xWPfVb4jV9XX7Vd4CM+V3oWWse1DgT36c0OtLMqN
sW2NzQEB7IcjDyrG6vPUDzkqjQOqBmQbK/0rBbdRK15TY7kL3VNk+fWDAgFs9sH3
MepmvI6IP2t1WaqAfZktx3P72aXXePuyU4zQyKRB7ViG4l6ClAv6jh6Kq5TeLkEE
2ayQ6z+YcVnRlyd8kbnR8djFvCqvLRwVvByW4jtR0QKBgQDvE59MoV6KdeIk+gQ2
ylB1cxX2BnLqM9w6xowElcY+L9SQKkaS7u5K4bxBqwY0pbdV2ujj+GXcZJm8oKtm
7mZ8gVx6RkV+LOiS6/NBbw8Q8QLbCBujjFEBSjrBo0VB1JCNU7TnN5VvxJ9/6X4T
3NRGOlqB5FXX6v4eWycA0iErPwKBgQDcRV5HSE7vYQ6hIgpOodQFw7EiJMbODwZh
e6nJeH8y8FVZgNQwdGtzH1jXP3YJw8TnfM0FJzS69GUH5j3QaTJM7Y8/NMA1YFzw
Jba+Sf6ulO0TR+EJjzEv9aN0R6kA7BjMEJ1WBS5GMgNlRJwK43Nj7Fgpi6sM3+nI
wK9aq4uZ6QKBgHSNL1crC+5zZIGo10vvMaqkYQZgINspbRTHgb8QvEyfQd4u3gP1
5wH6n5Vh7W1n6cL4Flt0RDSW7j6oDgv2d9pnHlDAX7rZk3PXN5gP9PAZs3I5bFZ4
awQwZuwIcH91LAtsPl1gByy5gRBy9V8sMVhED9+eMlxW66cAuVFQ8A+VAoGAI07N
00z1u0wzD7aTn2Ur9Gc3IO2rN3mnb8mJ6YPmG1Q0gl2n9nct6N1nwlbR0hzDzXh0
s0J0iW7yRu+uKVnyw5L+P7CEgYCUAqX8ueeBvh6V0p4yhroDV+1WLe9rRNGOKNRu
1Mo5UBwpxh7cUApCqjep9VK5TQFW+0SfwnhQ7AECgYEAqYd9K4g3eBuV0ZV30Nzg
RjTY46Ym3CFaJADvOQXKtiwGxIKQHAFtPkWsp3CNY1gp0HfXwUYRWv1dqX5vmqcr
qm4qYlXU9ya64wBYr9oXnhcuWph86Gl5W2R77VUJpmqb7f5sMY7Rzwoz7j2gfGM9
H34hfSfkqXU5L5g8f30wDyE=
-----END PRIVATE KEY-----
KEY,
            'client_email' => 'vision-ocr@scanwell-test.iam.gserviceaccount.com',
            'client_id' => '1234567890',
        ], JSON_THROW_ON_ERROR);
    }
}
