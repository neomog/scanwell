<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class IngredientImageExtractionService
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

        $ingredientsText = $this->extractIngredientsText($extractedText);

        if ($ingredientsText === null) {
            return null;
        }

        return [
            'ingredients_text' => $ingredientsText,
            'ingredients' => $this->splitIngredients($ingredientsText),
            'extracted_text' => $extractedText,
            'confidence' => isset($ocr['confidence']) && is_numeric($ocr['confidence'])
                ? round(max(0, min(1, (float) $ocr['confidence'])), 4)
                : null,
            'analysis_source' => $ocr['provider'] ?? 'google_cloud_vision',
            'ocr_mode' => $ocr['mode'] ?? 'DOCUMENT_TEXT_DETECTION',
        ];
    }

    protected function extractIngredientsText(string $text): ?string
    {
        $text = preg_replace('/\r\n?/', "\n", $text) ?? $text;
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $normalized = trim($text);

        if ($normalized === '') {
            return null;
        }

        $candidate = $this->extractKeywordSection($normalized);

        if ($candidate === null) {
            $candidate = $normalized;
        }

        $candidate = $this->cleanupIngredientsText($candidate);

        return $candidate === '' ? null : $candidate;
    }

    protected function extractKeywordSection(string $text): ?string
    {
        $patterns = [
            '/(?:^|\b)(?:ingredients?|ingredient list)\s*[:\-]?\s*(.+)$/is',
            '/(?:^|\b)(?:active ingredients?)\s*[:\-]?\s*(.+)$/is',
            '/(?:^|\b)(?:inactive ingredients?)\s*[:\-]?\s*(.+)$/is',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) !== 1) {
                continue;
            }

            return $this->truncateAtStopPhrase($matches[1] ?? '');
        }

        return null;
    }

    protected function truncateAtStopPhrase(string $text): string
    {
        $stopPatterns = [
            '/\bnutrition facts\b/i',
            '/\bsupplement facts\b/i',
            '/\bcontains\b/i',
            '/\ballergen(?:s)?\b/i',
            '/\bdistributed by\b/i',
            '/\bmanufactured by\b/i',
            '/\bwarning(?:s)?\b/i',
            '/\bdirections\b/i',
            '/\bstorage\b/i',
            '/\bserving size\b/i',
            '/\bkeep out of reach\b/i',
            '/\bbest before\b/i',
        ];

        $cutoff = strlen($text);

        foreach ($stopPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE) === 1) {
                $position = $matches[0][1];
                $cutoff = min($cutoff, $position);
            }
        }

        return substr($text, 0, $cutoff);
    }

    protected function cleanupIngredientsText(string $text): string
    {
        $text = str_replace(["\n", "\r"], ' ', $text);
        $text = preg_replace('/\s*,\s*/', ', ', $text) ?? $text;
        $text = preg_replace('/\s*;\s*/', '; ', $text) ?? $text;
        $text = preg_replace('/\(\s+/', '(', $text) ?? $text;
        $text = preg_replace('/\s+\)/', ')', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = preg_replace('/^(ingredients?|ingredient list|active ingredients?|inactive ingredients?)\s*[:\-]?\s*/i', '', trim($text)) ?? trim($text);
        $text = trim($text, " \t\n\r\0\x0B,;:-.");

        return trim($text);
    }

    protected function splitIngredients(string $ingredientsText): array
    {
        return collect(preg_split('/[,;]+/', $ingredientsText) ?: [])
            ->map(fn ($ingredient) => $this->cleanupIngredientItem($ingredient))
            ->filter()
            ->values()
            ->all();
    }

    protected function cleanupIngredientItem(string $ingredient): ?string
    {
        $ingredient = preg_replace('/\s+/', ' ', trim($ingredient)) ?? trim($ingredient);
        $ingredient = trim($ingredient, " \t\n\r\0\x0B.:-");

        return $ingredient === '' ? null : $ingredient;
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
