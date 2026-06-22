<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class IngredientImageExtractionService
{
    public function __construct(
        protected GoogleCloudVisionService $googleCloudVisionService,
        protected OpenAiIngredientLabelService $openAiIngredientLabelService,
        protected IngredientTextParserService $ingredientTextParserService
    ) {
    }

    public function extractFromImage(UploadedFile $image): ?array
    {
        $imageBinary = (string) file_get_contents($image->getRealPath());
        $mimeType = $image->getMimeType() ?: 'image/jpeg';

        $aiResult = $this->openAiIngredientLabelService->extractFromImage($imageBinary, $mimeType);

        if ($aiResult !== null) {
            return $aiResult;
        }

        $ocr = $this->googleCloudVisionService->analyzeProductImage(
            $imageBinary,
            $mimeType
        );

        $extractedText = $this->nullableString($ocr['extracted_text'] ?? null);

        if ($extractedText === null) {
            return null;
        }

        $documentLines = is_array($ocr['document_lines'] ?? null) ? $ocr['document_lines'] : [];

        $ingredientsText = $documentLines !== []
            ? $this->ingredientTextParserService->extractIngredientsTextFromLines($documentLines)
            : null;

        if ($ingredientsText === null) {
            $ingredientsText = $this->ingredientTextParserService->extractIngredientsText($extractedText);
        }

        if ($ingredientsText === null) {
            return null;
        }

        return [
            'ingredients_text' => $ingredientsText,
            'ingredients' => $this->ingredientTextParserService->splitIngredients($ingredientsText),
            'extracted_text' => $extractedText,
            'confidence' => isset($ocr['confidence']) && is_numeric($ocr['confidence'])
                ? round(max(0, min(1, (float) $ocr['confidence'])), 4)
                : null,
            'analysis_source' => $ocr['provider'] ?? 'google_cloud_vision',
            'ocr_mode' => $ocr['mode'] ?? 'DOCUMENT_TEXT_DETECTION',
        ];
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
