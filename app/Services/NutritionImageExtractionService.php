<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class NutritionImageExtractionService
{
    public function __construct(
        protected GoogleCloudVisionService $googleCloudVisionService
    ) {
    }

    public function extractFromImage(UploadedFile $image): ?array
    {
        $ocr = $this->googleCloudVisionService->analyzeProductImage(
            (string) file_get_contents($image->getRealPath()),
            $image->getMimeType() ?: 'image/jpeg'
        );

        $extractedText = $this->nullableString($ocr['extracted_text'] ?? null);

        if ($extractedText === null) {
            return null;
        }

        $nutrition = $this->extractNutrition($extractedText);

        if ($nutrition === []) {
            return null;
        }

        return [
            'nutrition' => $nutrition,
            'extracted_text' => $extractedText,
            'confidence' => isset($ocr['confidence']) && is_numeric($ocr['confidence'])
                ? round(max(0, min(1, (float) $ocr['confidence'])), 4)
                : null,
            'analysis_source' => $ocr['provider'] ?? 'google_cloud_vision',
            'ocr_mode' => $ocr['mode'] ?? 'DOCUMENT_TEXT_DETECTION',
        ];
    }

    protected function extractNutrition(string $text): array
    {
        $normalized = preg_replace('/\r\n?/', "\n", $text) ?? $text;
        $normalized = preg_replace('/[ \t]+/', ' ', $normalized) ?? $normalized;

        $nutrition = [];

        $stringFields = [
            'serving_size' => [
                '/\bserving\s*size\b\s*[:\-]?\s*([^\n]+)/i',
            ],
        ];

        $numericFields = [
            'calories' => [
                '/\bcalories\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
            ],
            'fat' => [
                '/\btotal\s+fat\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
                '/(?<!saturated\s)(?<!trans\s)\bfat\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
            ],
            'saturated_fat' => [
                '/\bsaturated\s+fat\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
            ],
            'trans_fat' => [
                '/\btrans\s+fat\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
            ],
            'cholesterol' => [
                '/\bcholesterol\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
            ],
            'sodium' => [
                '/\bsodium\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
            ],
            'carbohydrates' => [
                '/\btotal\s+carbohydrate(?:s)?\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
                '/\bcarbohydrate(?:s)?\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
            ],
            'fiber' => [
                '/\b(?:dietary\s+)?fiber\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
            ],
            'sugars' => [
                '/\btotal\s+sugars\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
                '/(?<!added\s)\bsugars?\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
            ],
            'added_sugars' => [
                '/\bincludes\s+(\d+(?:\.\d+)?)\s*g?\s+added\s+sugars?\b/i',
                '/\badded\s+sugars?\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
            ],
            'protein' => [
                '/\bprotein\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
            ],
        ];

        foreach ($stringFields as $key => $patterns) {
            $value = $this->extractFirstStringMatch($normalized, $patterns);

            if ($value !== null) {
                $nutrition[$key] = $value;
            }
        }

        foreach ($numericFields as $key => $patterns) {
            $value = $this->extractFirstNumericMatch($normalized, $patterns);

            if ($value !== null) {
                $nutrition[$key] = $value;
            }
        }

        return $nutrition;
    }

    protected function extractFirstStringMatch(string $text, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) !== 1) {
                continue;
            }

            $value = $this->cleanupStringValue($matches[1] ?? '');

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    protected function extractFirstNumericMatch(string $text, array $patterns): ?float
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) !== 1) {
                continue;
            }

            $value = $this->normalizeNumericValue($matches[1] ?? null);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    protected function normalizeNumericValue(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $candidate = trim((string) $value);

        if ($candidate === '' || !is_numeric($candidate)) {
            return null;
        }

        return (float) $candidate;
    }

    protected function cleanupStringValue(string $value): ?string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
        $value = preg_replace('/\b(?:amount per serving|calories|total fat|saturated fat|trans fat|cholesterol|sodium|total carbohydrate|dietary fiber|total sugars|protein)\b.*$/i', '', $value) ?? $value;
        $value = trim($value, " \t\n\r\0\x0B:;,-");

        return $value === '' ? null : $value;
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
