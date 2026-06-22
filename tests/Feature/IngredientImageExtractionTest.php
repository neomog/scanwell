<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IngredientImageExtractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_extracts_ingredients_text_from_uploaded_image(): void
    {
        Sanctum::actingAs(User::factory()->create());

        config()->set('services.openai.api_key', null);
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
                        'text' => "Nutrition Facts\nIngredients: Water, Sugar, Citric Acid, Natural Flavors\nContains: None",
                        'pages' => [[
                            'blocks' => [
                                ['confidence' => 0.94],
                            ],
                        ]],
                    ],
                ]],
            ]),
        ]);

        $response = $this->post('/api/v1/contributions/ingredients/extract', [
            'image' => UploadedFile::fake()->image('ingredients.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ingredients_text', 'Water, Sugar, Citric Acid, Natural Flavors')
            ->assertJsonPath('data.ingredients.0', 'Water')
            ->assertJsonPath('data.ingredients.3', 'Natural Flavors')
            ->assertJsonPath('data.analysis_source', 'google_cloud_vision')
            ->assertJsonPath('data.ocr_mode', 'DOCUMENT_TEXT_DETECTION');
    }

    public function test_it_returns_unprocessable_when_no_ingredient_text_can_be_extracted(): void
    {
        Sanctum::actingAs(User::factory()->create());

        config()->set('services.openai.api_key', null);
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
                        'text' => '',
                        'pages' => [[
                            'blocks' => [],
                        ]],
                    ],
                ]],
            ]),
        ]);

        $response = $this->post('/api/v1/contributions/ingredients/extract', [
            'image' => UploadedFile::fake()->image('blurry.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_google_vision_lines_can_reconstruct_wrapped_ingredient_lists(): void
    {
        Sanctum::actingAs(User::factory()->create());

        config()->set('services.openai.api_key', null);
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
                        'text' => "INGREDIENTS\nWater,\nSugar,\nCitric Acid,\nNatural Flavors\nContains: None",
                        'pages' => [[
                            'height' => 1000,
                            'blocks' => [[
                                'confidence' => 0.95,
                                'paragraphs' => [
                                    ['words' => [$this->fakeVisionWord('INGREDIENTS', 40, 100)]],
                                    ['words' => [$this->fakeVisionWord('Water,', 40, 140)]],
                                    ['words' => [$this->fakeVisionWord('Sugar,', 40, 180)]],
                                    ['words' => [$this->fakeVisionWord('Citric', 40, 220), $this->fakeVisionWord('Acid,', 120, 220)]],
                                    ['words' => [$this->fakeVisionWord('Natural', 40, 260), $this->fakeVisionWord('Flavors', 130, 260)]],
                                    ['words' => [$this->fakeVisionWord('Contains:', 40, 320), $this->fakeVisionWord('None', 150, 320)]],
                                ],
                            ]],
                        ]],
                    ],
                ]],
            ]),
        ]);

        $response = $this->post('/api/v1/contributions/ingredients/extract', [
            'image' => UploadedFile::fake()->image('ingredients-lines.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ingredients_text', 'Water, Sugar, Citric Acid, Natural Flavors')
            ->assertJsonPath('data.ingredients.0', 'Water')
            ->assertJsonPath('data.ingredients.3', 'Natural Flavors');
    }

    public function test_it_prefers_openai_structured_ingredient_extraction_when_available(): void
    {
        Sanctum::actingAs(User::factory()->create());

        config()->set('services.openai.api_key', 'test-openai-key');

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'content' => [[
                        'text' => json_encode([
                            'confidence' => 98,
                            'ingredients' => [
                                'Water',
                                'Sugar',
                                'Citric Acid',
                                'Natural Flavors',
                            ],
                        ], JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ]),
        ]);

        $response = $this->post('/api/v1/contributions/ingredients/extract', [
            'image' => UploadedFile::fake()->image('ingredients-openai.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ingredients_text', 'Water, Sugar, Citric Acid, Natural Flavors')
            ->assertJsonPath('data.ingredients.0', 'Water')
            ->assertJsonPath('data.ingredients.3', 'Natural Flavors')
            ->assertJsonPath('data.analysis_source', 'openai_vision')
            ->assertJsonPath('data.ocr_mode', 'structured_ingredients_json');
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

    protected function fakeVisionWord(string $text, int $x, int $y): array
    {
        $symbols = [];
        $cursor = $x;

        foreach (mb_str_split($text) as $character) {
            $symbols[] = [
                'text' => $character,
            ];
            $cursor += 12;
        }

        return [
            'symbols' => $symbols,
            'boundingBox' => [
                'vertices' => [
                    ['x' => $x, 'y' => $y],
                    ['x' => max($x + 10, $cursor), 'y' => $y],
                    ['x' => max($x + 10, $cursor), 'y' => $y + 20],
                    ['x' => $x, 'y' => $y + 20],
                ],
            ],
        ];
    }
}
