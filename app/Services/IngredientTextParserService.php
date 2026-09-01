<?php

namespace App\Services;

class IngredientTextParserService
{
    protected array $stopPatterns = [
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
        '/\bbarcode\b/i',
    ];

    public function extractIngredientsText(string $text): ?string
    {
        $normalized = $this->normalizeText($text);

        if ($normalized === null) {
            return null;
        }

        $candidate = $this->extractKeywordSection($normalized);

        if ($candidate === null) {
            $candidate = $normalized;
        }

        $candidate = $this->cleanupIngredientsText($candidate);

        return $candidate === '' ? null : $candidate;
    }

    public function extractIngredientsTextFromLines(array $lines): ?string
    {
        $prepared = collect($lines)
            ->map(fn ($line) => $this->cleanupLine(is_string($line) ? $line : ''))
            ->filter()
            ->values()
            ->all();

        if ($prepared === []) {
            return null;
        }

        $captured = [];
        $collecting = false;

        foreach ($prepared as $line) {
            if ($this->isStopLine($line)) {
                break;
            }

            if (!$collecting) {
                $inlineIngredients = $this->extractInlineIngredientsFromLine($line);

                if ($inlineIngredients !== null) {
                    $captured[] = $inlineIngredients;
                    $collecting = true;
                }

                continue;
            }

            $captured[] = $line;
        }

        if ($captured !== []) {
            return $this->extractIngredientsText(implode("\n", $captured));
        }

        return $this->extractIngredientsText(implode("\n", $prepared));
    }

    public function splitIngredients(string $ingredientsText): array
    {
        return collect(preg_split('/[,;]+/', $ingredientsText) ?: [])
            ->map(fn ($ingredient) => $this->cleanupIngredientItem($ingredient))
            ->filter()
            ->values()
            ->all();
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
        $cutoff = strlen($text);

        foreach ($this->stopPatterns as $pattern) {
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

    protected function cleanupIngredientItem(string $ingredient): ?string
    {
        $ingredient = preg_replace('/\s+/', ' ', trim($ingredient)) ?? trim($ingredient);
        $ingredient = trim($ingredient, " \t\n\r\0\x0B.:-");

        return $ingredient === '' ? null : $ingredient;
    }

    protected function normalizeText(string $text): ?string
    {
        $text = preg_replace('/\r\n?/', "\n", $text) ?? $text;
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = trim($text);

        return $text === '' ? null : $text;
    }

    protected function cleanupLine(string $line): ?string
    {
        $line = preg_replace('/\s+/', ' ', trim($line)) ?? trim($line);
        $line = trim($line, "*| \t\n\r\0\x0B");

        return $line === '' ? null : $line;
    }

    protected function isStopLine(string $line): bool
    {
        foreach ($this->stopPatterns as $pattern) {
            if (preg_match($pattern, $line) === 1) {
                return true;
            }
        }

        return false;
    }

    protected function extractInlineIngredientsFromLine(string $line): ?string
    {
        if (preg_match('/^(ingredients?|ingredient list|active ingredients?|inactive ingredients?)\s*[:\-]?\s*(.*)$/i', $line, $matches) !== 1) {
            return null;
        }

        $candidate = trim((string) ($matches[2] ?? ''));

        return $candidate === '' ? '' : $candidate;
    }
}
