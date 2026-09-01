<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class NutritionImageExtractionService
{
    public function __construct(
        protected GoogleCloudVisionService $googleCloudVisionService,
        protected OpenAiNutritionLabelService $openAiNutritionLabelService,
        protected NutritionTextParserService $nutritionTextParserService
    ) {
    }

    public function extractFromImage(UploadedFile $image): ?array
    {
        $imageBinary = (string) file_get_contents($image->getRealPath());
        $mimeType = $image->getMimeType() ?: 'image/jpeg';

        $aiResult = $this->openAiNutritionLabelService->extractFromImage($imageBinary, $mimeType);

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

        $nutritionText = $documentLines !== []
            ? $this->nutritionTextParserService->extractNutritionTextFromLines($documentLines)
            : null;

        if ($nutritionText === null) {
            $nutritionText = $this->nutritionTextParserService->extractNutritionText($extractedText);
        }

        if ($nutritionText === null) {
            return null;
        }

        $nutrition = $documentLines !== []
            ? $this->nutritionTextParserService->parseFromLines($documentLines)
            : [];

        if ($nutrition === []) {
            $nutrition = $this->nutritionTextParserService->parse($nutritionText);
        }

        return [
            'nutrition_text' => $nutritionText,
            'nutrition' => $nutrition,
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
