<?php

namespace App\Services;

class NutritionTextParserService
{
    protected array $fieldAliases = [
        'calories' => ['calories', 'energy'],
        'fat' => ['total fat', 'fat'],
        'saturated_fat' => ['saturated fat'],
        'trans_fat' => ['trans fat'],
        'cholesterol' => ['cholesterol'],
        'sodium' => ['sodium', 'salt'],
        'carbohydrates' => ['total carbohydrate', 'total carbohydrates', 'carbohydrate', 'carbohydrates', 'carbs'],
        'fiber' => ['dietary fiber', 'dietary fibre', 'fiber', 'fibre'],
        'sugars' => ['total sugars', 'sugars', 'sugar'],
        'added_sugars' => ['added sugars', 'added sugar'],
        'protein' => ['protein'],
        'vitamin_d' => ['vitamin d'],
        'calcium' => ['calcium'],
        'iron' => ['iron'],
        'potassium' => ['potassium'],
    ];

    public function extractNutritionText(string $text): ?string
    {
        $normalized = $this->normalizeText($text);

        if ($normalized === null) {
            return null;
        }

        $lines = $this->extractRelevantLines($normalized);

        if ($lines === []) {
            return null;
        }

        return implode("\n", $lines);
    }

    public function extractNutritionTextFromLines(array $lines): ?string
    {
        $prepared = $this->prepareCandidateLines($lines);

        if ($prepared === []) {
            return null;
        }

        $merged = $this->mergeSplitTableRows($prepared);

        if ($merged === []) {
            return null;
        }

        return implode("\n", $merged);
    }

    public function parse(string $text): array
    {
        $normalized = $this->normalizeText($text);

        if ($normalized === null) {
            return [];
        }

        $lines = $this->extractRelevantLines($normalized);
        $nutrition = [];

        foreach ($lines as $line) {
            if (!isset($nutrition['serving_size'])) {
                $servingSize = $this->extractServingSizeFromLine($line);

                if ($servingSize !== null) {
                    $nutrition['serving_size'] = $servingSize;
                }
            }

            foreach ($this->fieldAliases as $field => $aliases) {
                if (isset($nutrition[$field])) {
                    continue;
                }

                if (!$this->lineMatchesAnyAlias($line, $aliases)) {
                    continue;
                }

                $value = $this->extractValueForField($field, $line);

                if ($value !== null) {
                    $nutrition[$field] = $value;
                }
            }
        }

        foreach ($this->buildFallbackPatterns() as $field => $patterns) {
            if (isset($nutrition[$field])) {
                continue;
            }

            $value = $this->extractFirstNumericMatch($normalized, $patterns);

            if ($value !== null) {
                $nutrition[$field] = $value;
            }
        }

        if (!isset($nutrition['serving_size'])) {
            $servingSize = $this->extractFirstStringMatch($normalized, [
                '/\bserving\s*size\b\s*[:\-]?\s*([^\n,;]+)/i',
                '/\bper\s+(\d+(?:\.\d+)?)\s*(g|ml)\b/i',
            ]);

            if ($servingSize !== null) {
                $nutrition['serving_size'] = $servingSize;
            }
        }

        return $nutrition;
    }

    public function parseFromLines(array $lines): array
    {
        $prepared = $this->prepareCandidateLines($lines);

        if ($prepared === []) {
            return [];
        }

        $merged = $this->mergeSplitTableRows($prepared);

        return $this->parse(implode("\n", $merged));
    }

    protected function extractRelevantLines(string $text): array
    {
        $rawLines = preg_split('/[\n;,]+/', $text) ?: [];
        return $this->mergeSplitTableRows($this->prepareCandidateLines($rawLines));
    }

    protected function prepareCandidateLines(array $rawLines): array
    {
        $candidateLines = [];

        foreach ($rawLines as $rawLine) {
            $line = $this->cleanupLine(is_string($rawLine) ? $rawLine : '');

            if ($line === null) {
                continue;
            }

            if ($this->isLikelyRelevantNutritionLine($line) || $this->isLikelyNumericValueLine($line)) {
                $candidateLines[] = $line;
            }
        }

        return $candidateLines;
    }

    protected function isLikelyRelevantNutritionLine(string $line): bool
    {
        if ($this->extractServingSizeFromLine($line) !== null) {
            return true;
        }

        foreach ($this->fieldAliases as $aliases) {
            if ($this->lineMatchesAnyAlias($line, $aliases)) {
                return true;
            }
        }

        return false;
    }

    protected function isHeaderLikeNutritionLine(string $line): bool
    {
        $normalized = $this->normalizeLabel($line);

        return str_contains($normalized, 'nutrition')
            || str_contains($normalized, 'nutritional information')
            || str_contains($normalized, 'per 100g')
            || str_contains($normalized, 'per 100 g')
            || str_contains($normalized, 'per 100ml')
            || str_contains($normalized, 'per 100 ml');
    }

    protected function isLikelyNumericValueLine(string $line): bool
    {
        if (preg_match('/^\d+(?:\.\d+)?\s*(?:kcal|kj|g|mg|mcg|iu|ml|%)?$/i', $line) === 1) {
            return true;
        }

        return false;
    }

    protected function mergeSplitTableRows(array $lines): array
    {
        $columnMerged = $this->mergeColumnSeparatedRows($lines);

        if ($columnMerged !== null) {
            return array_values(array_unique($columnMerged));
        }

        $merged = [];
        $count = count($lines);

        for ($index = 0; $index < $count; $index++) {
            $line = $lines[$index];

            if ($this->isLikelyNumericValueLine($line)) {
                if ($merged !== []) {
                    $lastIndex = count($merged) - 1;

                    if (
                        $this->isLikelyRelevantNutritionLine($merged[$lastIndex]) &&
                        !$this->lineContainsNumericValue($merged[$lastIndex])
                    ) {
                        $merged[$lastIndex] .= ' ' . $line;
                        continue;
                    }
                }

                continue;
            }

            if (
                $index + 1 < $count &&
                $this->isLikelyRelevantNutritionLine($line) &&
                !$this->lineContainsNumericValue($line) &&
                $this->isLikelyNumericValueLine($lines[$index + 1])
            ) {
                $merged[] = $line . ' ' . $lines[$index + 1];
                $index++;
                continue;
            }

            if ($this->isLikelyRelevantNutritionLine($line)) {
                $merged[] = $line;
            }
        }

        return array_values(array_unique($merged));
    }

    protected function mergeColumnSeparatedRows(array $lines): ?array
    {
        if (count($lines) < 4) {
            return null;
        }

        $headerLines = [];
        $startIndex = 0;

        while ($startIndex < count($lines) && $this->isHeaderLikeNutritionLine($lines[$startIndex])) {
            $headerLines[] = $lines[$startIndex];
            $startIndex++;
        }

        if ($startIndex >= count($lines)) {
            return null;
        }

        $firstNumericIndex = null;

        for ($index = $startIndex; $index < count($lines); $index++) {
            if ($this->isLikelyNumericValueLine($lines[$index])) {
                $firstNumericIndex = $index;
                break;
            }
        }

        if ($firstNumericIndex === null || $firstNumericIndex === $startIndex) {
            return null;
        }

        $labelLines = array_slice($lines, $startIndex, $firstNumericIndex - $startIndex);
        $valueLines = array_slice($lines, $firstNumericIndex);

        if (count($labelLines) < 2 || count($valueLines) < 2) {
            return null;
        }

        foreach ($labelLines as $line) {
            if (!$this->isLikelyRelevantNutritionLine($line) || $this->lineContainsNumericValue($line)) {
                return null;
            }
        }

        foreach ($valueLines as $line) {
            if (!$this->isLikelyNumericValueLine($line)) {
                return null;
            }
        }

        $merged = $headerLines;
        $pairCount = min(count($labelLines), count($valueLines));

        for ($index = 0; $index < $pairCount; $index++) {
            $merged[] = $labelLines[$index] . ' ' . $valueLines[$index];
        }

        return $merged;
    }

    protected function extractValueForField(string $field, string $line): ?float
    {
        if ($field === 'calories') {
            if (preg_match('/(\d+(?:\.\d+)?)\s*kcal\b/i', $line, $matches) === 1) {
                return $this->normalizeNumericValue($matches[1] ?? null);
            }
        }

        if ($field === 'vitamin_d') {
            if (preg_match('/(\d+(?:\.\d+)?)\s*(?:iu|mcg)\b/i', $line, $matches) === 1) {
                return $this->normalizeNumericValue($matches[1] ?? null);
            }
        }

        if ($field === 'sodium' || $field === 'cholesterol' || $field === 'calcium' || $field === 'iron' || $field === 'potassium') {
            if (preg_match('/(\d+(?:\.\d+)?)\s*mg\b/i', $line, $matches) === 1) {
                return $this->normalizeNumericValue($matches[1] ?? null);
            }
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*g\b/i', $line, $matches) === 1) {
            return $this->normalizeNumericValue($matches[1] ?? null);
        }

        if (preg_match_all('/\d+(?:\.\d+)?/', $line, $matches) !== 1) {
            return null;
        }

        $values = $matches[0] ?? [];

        if ($values === []) {
            return null;
        }

        return $this->normalizeNumericValue(end($values));
    }

    protected function lineContainsNumericValue(string $line): bool
    {
        return preg_match('/\d+(?:\.\d+)?/', $line) === 1;
    }

    protected function buildFallbackPatterns(): array
    {
        return [
            'calories' => [
                '/\bcalories\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
                '/\benergy\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
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
                '/\bsalt\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
            ],
            'carbohydrates' => [
                '/\btotal\s+carbohydrate(?:s)?\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
                '/\bcarbohydrate(?:s)?\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
            ],
            'fiber' => [
                '/\b(?:dietary\s+)?fiber\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
                '/\bfibre\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
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
            'vitamin_d' => [
                '/\bvitamin\s*d\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
            ],
            'calcium' => [
                '/\bcalcium\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
            ],
            'iron' => [
                '/\biron\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
            ],
            'potassium' => [
                '/\bpotassium\b\s*[:\-]?\s*(\d+(?:\.\d+)?)/i',
            ],
        ];
    }

    protected function extractServingSizeFromLine(string $line): ?string
    {
        if (preg_match('/\bserving\s*size\b\s*[:\-]?\s*(.+)$/i', $line, $matches) === 1) {
            return $this->cleanupStringValue($matches[1] ?? '');
        }

        if (preg_match('/\bper\s+(\d+(?:\.\d+)?)\s*(g|ml)\b/i', $line, $matches) === 1) {
            return trim(($matches[1] ?? '') . ' ' . strtolower($matches[2] ?? ''));
        }

        return null;
    }

    protected function lineMatchesAnyAlias(string $line, array $aliases): bool
    {
        $normalizedLine = $this->normalizeLabel($line);

        foreach ($aliases as $alias) {
            $normalizedAlias = $this->normalizeLabel($alias);

            if ($normalizedAlias !== '' && str_contains($normalizedLine, $normalizedAlias)) {
                return true;
            }
        }

        return false;
    }

    protected function extractFirstStringMatch(string $text, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) !== 1) {
                continue;
            }

            if (count($matches) >= 3 && isset($matches[2])) {
                $candidate = trim(($matches[1] ?? '') . ' ' . strtolower($matches[2] ?? ''));
                return $candidate === '' ? null : $candidate;
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

    protected function cleanupStringValue(string $value): ?string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
        $value = trim($value, " \t\n\r\0\x0B:;,-");

        return $value === '' ? null : $value;
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

    protected function normalizeLabel(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim($value);
    }
}
