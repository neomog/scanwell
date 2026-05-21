<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleCloudVisionService
{
    public function analyzeProductImage(string $imageBinary, string $mimeType = 'image/jpeg'): ?array
    {
        $credentials = $this->resolveCredentials();

        if ($credentials === null) {
            return null;
        }

        $accessToken = $this->accessToken($credentials);

        if ($accessToken === null) {
            return null;
        }

        $payload = [
            'requests' => [[
                'image' => [
                    'content' => base64_encode($imageBinary),
                ],
                'features' => [[
                    'type' => 'DOCUMENT_TEXT_DETECTION',
                ]],
            ]],
        ];

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout((int) config('services.google_cloud_vision.timeout', 30))
                ->post(rtrim((string) config('services.google_cloud_vision.base_url', 'https://vision.googleapis.com/v1'), '/') . '/images:annotate', $payload);

            if (!$response->successful()) {
                Log::warning('Google Cloud Vision OCR request failed', [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return null;
            }

            return $this->normalizeResult($response->json());
        } catch (\Throwable $exception) {
            Log::error('Google Cloud Vision OCR request errored', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function normalizeResult(array $response): ?array
    {
        $annotation = data_get($response, 'responses.0');
        $fullText = $this->nullableString(data_get($annotation, 'fullTextAnnotation.text'))
            ?? $this->nullableString(data_get($annotation, 'textAnnotations.0.description'));

        if ($fullText === null) {
            return null;
        }

        $guesses = $this->guessIdentityFromText($fullText);

        return [
            'extracted_text' => $fullText,
            'product_name' => $guesses['product_name'],
            'brand' => $guesses['brand'],
            'barcode_hint' => $this->extractBarcodeHint($fullText),
            'confidence' => $this->extractConfidence($annotation),
            'provider' => 'google_cloud_vision',
            'mode' => 'DOCUMENT_TEXT_DETECTION',
        ];
    }

    protected function accessToken(array $credentials): ?string
    {
        $cacheKey = 'scanwell:google-cloud-vision:token:' . sha1(($credentials['client_email'] ?? '') . '|' . ($credentials['private_key_id'] ?? ''));

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($credentials) {
            try {
                $response = Http::asForm()
                    ->acceptJson()
                    ->timeout((int) config('services.google_cloud_vision.timeout', 30))
                    ->post((string) config('services.google_cloud_vision.token_url', 'https://oauth2.googleapis.com/token'), [
                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                        'assertion' => $this->buildJwtAssertion($credentials),
                    ]);

                if (!$response->successful()) {
                    Log::warning('Google OAuth token request failed', [
                        'status' => $response->status(),
                        'body' => $response->json() ?? $response->body(),
                    ]);

                    return null;
                }

                return $this->nullableString(data_get($response->json(), 'access_token'));
            } catch (\Throwable $exception) {
                Log::error('Google OAuth token request errored', [
                    'error' => $exception->getMessage(),
                ]);

                return null;
            }
        });
    }

    protected function buildJwtAssertion(array $credentials): string
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + 3600;

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));

        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud' => (string) config('services.google_cloud_vision.token_url', 'https://oauth2.googleapis.com/token'),
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ], JSON_THROW_ON_ERROR));

        $unsignedToken = $header . '.' . $claims;
        $signature = '';

        $signed = openssl_sign($unsignedToken, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);

        if ($signed !== true) {
            throw new \RuntimeException('Unable to sign Google Cloud Vision JWT assertion.');
        }

        return $unsignedToken . '.' . $this->base64UrlEncode($signature);
    }

    protected function resolveCredentials(): ?array
    {
        $inlineJson = $this->nullableString(config('services.google_cloud_vision.credentials_json'));
        $credentialsPath = $this->nullableString(config('services.google_cloud_vision.credentials_path'));

        if ($inlineJson !== null) {
            $decoded = json_decode($inlineJson, true);

            return is_array($decoded) ? $decoded : null;
        }

        if ($credentialsPath !== null && is_file($credentialsPath)) {
            $decoded = json_decode((string) file_get_contents($credentialsPath), true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    protected function guessIdentityFromText(string $text): array
    {
        $lines = collect(preg_split('/\R+/', $text) ?: [])
            ->map(fn ($line) => $this->cleanLine($line))
            ->filter()
            ->reject(fn ($line) => $this->isLikelyNoiseLine($line))
            ->unique()
            ->values();

        $brand = $lines->get(0);
        $productName = $lines->get(1) ?? $lines->get(0);

        if ($brand !== null && $productName !== null && $this->normalize($brand) === $this->normalize($productName)) {
            $brand = null;
        }

        return [
            'brand' => $brand,
            'product_name' => $productName,
        ];
    }

    protected function extractBarcodeHint(string $text): ?string
    {
        preg_match_all('/(?<!\d)(\d{8,13})(?!\d)/', $text, $matches);

        foreach ($matches[1] ?? [] as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    protected function extractConfidence(array $annotation): float
    {
        $confidences = [];

        foreach (data_get($annotation, 'fullTextAnnotation.pages', []) as $page) {
            foreach (($page['blocks'] ?? []) as $block) {
                $confidence = $block['confidence'] ?? null;

                if (is_numeric($confidence)) {
                    $confidences[] = (float) $confidence;
                }
            }
        }

        if ($confidences === []) {
            return 0.0;
        }

        return round(array_sum($confidences) / count($confidences), 4);
    }

    protected function cleanLine(string $line): ?string
    {
        $line = preg_replace('/\s+/', ' ', trim($line));

        return $line === '' ? null : $line;
    }

    protected function isLikelyNoiseLine(string $line): bool
    {
        $normalized = $this->normalize($line);

        if ($normalized === '') {
            return true;
        }

        if (preg_match('/^\d{8,13}$/', $normalized) === 1) {
            return true;
        }

        foreach ([
            'nutrition facts',
            'ingredients',
            'supplement facts',
            'serving size',
            'amount per serving',
            'distributed by',
            'manufactured by',
            'best before',
            'warning',
            'directions',
        ] as $phrase) {
            if (str_contains($normalized, $phrase)) {
                return true;
            }
        }

        return false;
    }

    protected function normalize(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim($value);
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
