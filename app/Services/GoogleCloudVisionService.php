<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleCloudVisionService
{
    public function __construct(
        protected ScanProviderRuntimeConfigService $runtimeConfig
    ) {
    }

    public function analyzeProductImage(string $imageBinary, string $mimeType = 'image/jpeg'): ?array
    {
        $settings = $this->runtimeConfig->googleCloudVision();

        if (!(bool) ($settings['is_active'] ?? true)) {
            return null;
        }

        $credentials = $this->resolveCredentials($settings);

        if ($credentials === null) {
            return null;
        }

        $accessToken = $this->accessToken($credentials, $settings);

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
                ->retry((int) ($settings['retry_attempts'] ?? 1), 200)
                ->timeout((int) ($settings['timeout'] ?? 30))
                ->post(rtrim((string) ($settings['base_url'] ?? 'https://vision.googleapis.com/v1'), '/') . '/images:annotate', $payload);

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
            'document_lines' => $this->extractDocumentLines($annotation),
            'product_name' => $guesses['product_name'],
            'brand' => $guesses['brand'],
            'barcode_hint' => $this->extractBarcodeHint($fullText),
            'confidence' => $this->extractConfidence($annotation),
            'provider' => 'google_cloud_vision',
            'mode' => 'DOCUMENT_TEXT_DETECTION',
        ];
    }

    protected function accessToken(array $credentials, ?array $settings = null): ?string
    {
        $settings ??= $this->runtimeConfig->googleCloudVision();
        $cacheKey = 'scanwell:google-cloud-vision:token:' . sha1(($credentials['client_email'] ?? '') . '|' . ($credentials['private_key_id'] ?? ''));

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($credentials, $settings) {
            try {
                $response = Http::asForm()
                    ->acceptJson()
                    ->retry((int) ($settings['retry_attempts'] ?? 1), 200)
                    ->timeout((int) ($settings['timeout'] ?? 30))
                    ->post((string) ($settings['token_url'] ?? 'https://oauth2.googleapis.com/token'), [
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
            'aud' => (string) ($this->runtimeConfig->googleCloudVision()['token_url'] ?? 'https://oauth2.googleapis.com/token'),
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

    protected function resolveCredentials(?array $settings = null): ?array
    {
        $settings ??= $this->runtimeConfig->googleCloudVision();
        $inlineJson = $this->nullableString($settings['credentials_json'] ?? null);
        $credentialsPath = $this->nullableString($settings['credentials_path'] ?? null);

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

    protected function extractDocumentLines(array $annotation): array
    {
        $lines = [];

        foreach (data_get($annotation, 'fullTextAnnotation.pages', []) as $page) {
            $pageWords = [];

            foreach (($page['blocks'] ?? []) as $block) {
                foreach (($block['paragraphs'] ?? []) as $paragraph) {
                    foreach (($paragraph['words'] ?? []) as $word) {
                        $text = $this->extractWordText($word);
                        $box = $this->extractBoundingBox($word['boundingBox']['vertices'] ?? []);

                        if ($text === null || $box === null) {
                            continue;
                        }

                        $pageWords[] = [
                            'text' => $text,
                            'x_min' => $box['x_min'],
                            'x_max' => $box['x_max'],
                            'y_center' => ($box['y_min'] + $box['y_max']) / 2,
                        ];
                    }
                }
            }

            if ($pageWords === []) {
                continue;
            }

            usort($pageWords, function (array $a, array $b): int {
                if (abs($a['y_center'] - $b['y_center']) < 0.001) {
                    return $a['x_min'] <=> $b['x_min'];
                }

                return $a['y_center'] <=> $b['y_center'];
            });

            $rowThreshold = max(10.0, ((float) ($page['height'] ?? 1000)) * 0.015);
            $rows = [];

            foreach ($pageWords as $word) {
                $placed = false;

                foreach ($rows as &$row) {
                    if (abs($row['y_center'] - $word['y_center']) <= $rowThreshold) {
                        $row['words'][] = $word;
                        $row['y_center'] = ($row['y_center'] + $word['y_center']) / 2;
                        $placed = true;
                        break;
                    }
                }
                unset($row);

                if (!$placed) {
                    $rows[] = [
                        'y_center' => $word['y_center'],
                        'words' => [$word],
                    ];
                }
            }

            foreach ($rows as $row) {
                usort($row['words'], fn (array $a, array $b): int => $a['x_min'] <=> $b['x_min']);

                $line = collect($row['words'])
                    ->pluck('text')
                    ->filter()
                    ->implode(' ');

                $line = $this->cleanLine($line);

                if ($line !== null) {
                    $lines[] = $line;
                }
            }
        }

        return array_values(array_unique($lines));
    }

    protected function extractWordText(array $word): ?string
    {
        $symbols = $word['symbols'] ?? [];

        if (!is_array($symbols) || $symbols === []) {
            return null;
        }

        $text = collect($symbols)
            ->map(fn ($symbol) => is_array($symbol) ? ($symbol['text'] ?? '') : '')
            ->implode('');

        return $this->cleanLine($text);
    }

    protected function extractBoundingBox(array $vertices): ?array
    {
        if ($vertices === []) {
            return null;
        }

        $xs = [];
        $ys = [];

        foreach ($vertices as $vertex) {
            if (!is_array($vertex)) {
                continue;
            }

            $xs[] = (float) ($vertex['x'] ?? 0);
            $ys[] = (float) ($vertex['y'] ?? 0);
        }

        if ($xs === [] || $ys === []) {
            return null;
        }

        return [
            'x_min' => min($xs),
            'x_max' => max($xs),
            'y_min' => min($ys),
            'y_max' => max($ys),
        ];
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
