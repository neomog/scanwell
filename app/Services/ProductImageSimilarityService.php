<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductImageSimilarityService
{
    public function scoreProductsAgainstImage(string $imageBinary, iterable $products): array
    {
        $sourceSignature = $this->buildImageSignature($imageBinary);

        if ($sourceSignature === null) {
            Log::warning('Product image similarity source signature generation failed');
            return [];
        }

        $scores = [];

        foreach ($products as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $best = $this->bestSimilarityForProduct($sourceSignature, $product);

            if ($best === null) {
                continue;
            }

            $scores[$product->id] = $best;
        }

        return $scores;
    }

    protected function bestSimilarityForProduct(array $sourceSignature, Product $product): ?array
    {
        $images = $product->relationLoaded('images')
            ? $product->images
            : $product->images()->get();

        Log::debug('Product image similarity evaluating product images', [
            'product_id' => $product->id,
            'barcode' => $product->barcode,
            'image_count' => $images->count(),
        ]);

        $best = null;

        foreach ($images as $image) {
            $candidateSignature = $this->cachedSignatureForStoredImage($image->disk, $image->path, $image->url);

            if ($candidateSignature === null) {
                continue;
            }

            $similarity = $this->signatureSimilarity($sourceSignature, $candidateSignature);

            if ($best === null || $similarity > $best['similarity']) {
                $best = [
                    'similarity' => round($similarity, 4),
                    'image_id' => $image->id,
                    'image_url' => $image->resolved_url,
                ];
            }
        }

        return $best;
    }

    protected function loadProductImageBinary(?string $disk, ?string $path, ?string $url): ?string
    {
        try {
            if ($disk && $path && Storage::disk($disk)->exists($path)) {
                $binary = Storage::disk($disk)->get($path);

                return is_string($binary) && $binary !== '' ? $binary : null;
            }

            if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                $response = Http::timeout(10)->get($url);

                if ($response->successful()) {
                    $body = $response->body();

                    return is_string($body) && $body !== '' ? $body : null;
                }
            }
        } catch (\Throwable $exception) {
            Log::debug('Product image similarity candidate load failed', [
                'disk' => $disk,
                'path' => $path,
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);
        }

        return null;
    }

    protected function cachedSignatureForStoredImage(?string $disk, ?string $path, ?string $url): ?array
    {
        $cacheKey = 'scanwell:image-signature:' . sha1(($disk ?? '') . '|' . ($path ?? '') . '|' . ($url ?? ''));
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $imageBinary = $this->loadProductImageBinary($disk, $path, $url);

        if ($imageBinary === null) {
            return null;
        }

        $signature = $this->buildImageSignature($imageBinary);

        if ($signature !== null) {
            Cache::put($cacheKey, $signature, now()->addDays(7));
        }

        return $signature;
    }

    protected function buildImageSignature(string $imageBinary): ?array
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring($imageBinary);

        if ($image === false) {
            return null;
        }

        $width = max(1, imagesx($image));
        $height = max(1, imagesy($image));
        $centerCrop = $this->cropCenterImage($image, 0.78);

        $signature = [
            'dhash_full' => $this->differenceHashForImage($image),
            'dhash_center' => $centerCrop ? $this->differenceHashForImage($centerCrop) : null,
            'ahash_full' => $this->averageHashForImage($image),
            'grid' => $this->grayscaleGridForImage($image, 4, 4),
            'mean_rgb' => $this->meanRgbForImage($image, 16, 16),
            'ratio' => round($width / max(1, $height), 4),
        ];

        if ($centerCrop !== null) {
            imagedestroy($centerCrop);
        }

        imagedestroy($image);

        return $signature['dhash_full'] === null
            && $signature['dhash_center'] === null
            && $signature['ahash_full'] === null
            ? null
            : $signature;
    }

    protected function cropCenterImage($image, float $fraction)
    {
        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);

        $cropWidth = max(1, (int) round($sourceWidth * $fraction));
        $cropHeight = max(1, (int) round($sourceHeight * $fraction));
        $srcX = max(0, (int) floor(($sourceWidth - $cropWidth) / 2));
        $srcY = max(0, (int) floor(($sourceHeight - $cropHeight) / 2));

        $crop = imagecreatetruecolor($cropWidth, $cropHeight);

        if ($crop === false) {
            return null;
        }

        imagecopyresampled($crop, $image, 0, 0, $srcX, $srcY, $cropWidth, $cropHeight, $cropWidth, $cropHeight);

        return $crop;
    }

    protected function differenceHashForImage($image): ?string
    {
        $grid = $this->grayscaleGridForImage($image, 9, 8);

        if ($grid === null) {
            return null;
        }

        $bits = '';

        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $left = $grid[$y][$x] ?? null;
                $right = $grid[$y][$x + 1] ?? null;

                if ($left === null || $right === null) {
                    return null;
                }

                $bits .= $left > $right ? '1' : '0';
            }
        }

        return $bits === '' ? null : $bits;
    }

    protected function averageHashForImage($image): ?string
    {
        $grid = $this->grayscaleGridForImage($image, 8, 8);

        if ($grid === null) {
            return null;
        }

        $values = [];

        foreach ($grid as $row) {
            foreach ($row as $value) {
                $values[] = $value;
            }
        }

        if ($values === []) {
            return null;
        }

        $average = array_sum($values) / count($values);
        $bits = '';

        foreach ($values as $value) {
            $bits .= $value >= $average ? '1' : '0';
        }

        return $bits === '' ? null : $bits;
    }

    protected function grayscaleGridForImage($image, int $targetWidth, int $targetHeight): ?array
    {
        $thumb = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($thumb === false) {
            return null;
        }

        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, imagesx($image), imagesy($image));

        $grid = [];

        for ($y = 0; $y < $targetHeight; $y++) {
            $row = [];

            for ($x = 0; $x < $targetWidth; $x++) {
                $rgb = imagecolorat($thumb, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $row[] = (int) round(($r * 0.299) + ($g * 0.587) + ($b * 0.114));
            }

            $grid[] = $row;
        }

        imagedestroy($thumb);

        return $grid;
    }

    protected function meanRgbForImage($image, int $targetWidth, int $targetHeight): ?array
    {
        $thumb = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($thumb === false) {
            return null;
        }

        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, imagesx($image), imagesy($image));

        $pixels = max(1, $targetWidth * $targetHeight);
        $sumR = 0;
        $sumG = 0;
        $sumB = 0;

        for ($y = 0; $y < $targetHeight; $y++) {
            for ($x = 0; $x < $targetWidth; $x++) {
                $rgb = imagecolorat($thumb, $x, $y);
                $sumR += ($rgb >> 16) & 0xFF;
                $sumG += ($rgb >> 8) & 0xFF;
                $sumB += $rgb & 0xFF;
            }
        }

        imagedestroy($thumb);

        return [
            round(($sumR / $pixels) / 255, 4),
            round(($sumG / $pixels) / 255, 4),
            round(($sumB / $pixels) / 255, 4),
        ];
    }

    protected function signatureSimilarity(array $left, array $right): float
    {
        $weighted = [
            [$this->hashSimilarity((string) ($left['dhash_full'] ?? ''), (string) ($right['dhash_full'] ?? '')), 0.3],
            [$this->hashSimilarity((string) ($left['dhash_center'] ?? ''), (string) ($right['dhash_center'] ?? '')), 0.25],
            [$this->hashSimilarity((string) ($left['ahash_full'] ?? ''), (string) ($right['ahash_full'] ?? '')), 0.15],
            [$this->gridSimilarity($left['grid'] ?? null, $right['grid'] ?? null), 0.2],
            [$this->meanColorSimilarity($left['mean_rgb'] ?? null, $right['mean_rgb'] ?? null), 0.07],
            [$this->ratioSimilarity($left['ratio'] ?? null, $right['ratio'] ?? null), 0.03],
        ];

        $score = 0.0;
        $weightTotal = 0.0;

        foreach ($weighted as [$component, $weight]) {
            if (!is_numeric($component)) {
                continue;
            }

            $score += ((float) $component) * $weight;
            $weightTotal += $weight;
        }

        if ($weightTotal <= 0.0) {
            return 0.0;
        }

        return max(0.0, min(1.0, $score / $weightTotal));
    }

    protected function hashSimilarity(string $left, string $right): float
    {
        $length = min(strlen($left), strlen($right));

        if ($length === 0) {
            return 0.0;
        }

        $distance = 0;

        for ($index = 0; $index < $length; $index++) {
            if ($left[$index] !== $right[$index]) {
                $distance++;
            }
        }

        return max(0.0, 1.0 - ($distance / $length));
    }

    protected function gridSimilarity(?array $left, ?array $right): ?float
    {
        if (!is_array($left) || !is_array($right) || $left === [] || $right === []) {
            return null;
        }

        $rows = min(count($left), count($right));
        $distance = 0.0;
        $count = 0;

        for ($y = 0; $y < $rows; $y++) {
            $leftRow = is_array($left[$y] ?? null) ? $left[$y] : [];
            $rightRow = is_array($right[$y] ?? null) ? $right[$y] : [];
            $columns = min(count($leftRow), count($rightRow));

            for ($x = 0; $x < $columns; $x++) {
                $distance += abs(((int) $leftRow[$x]) - ((int) $rightRow[$x]));
                $count++;
            }
        }

        if ($count === 0) {
            return null;
        }

        return max(0.0, 1.0 - ($distance / ($count * 255)));
    }

    protected function meanColorSimilarity(?array $left, ?array $right): ?float
    {
        if (!is_array($left) || !is_array($right) || count($left) < 3 || count($right) < 3) {
            return null;
        }

        $dr = ((float) $left[0]) - ((float) $right[0]);
        $dg = ((float) $left[1]) - ((float) $right[1]);
        $db = ((float) $left[2]) - ((float) $right[2]);
        $distance = sqrt(($dr * $dr) + ($dg * $dg) + ($db * $db));
        $normalized = min(1.0, $distance / sqrt(3));

        return max(0.0, 1.0 - $normalized);
    }

    protected function ratioSimilarity(mixed $left, mixed $right): ?float
    {
        if (!is_numeric($left) || !is_numeric($right)) {
            return null;
        }

        $left = max(0.0001, (float) $left);
        $right = max(0.0001, (float) $right);
        $difference = abs($left - $right) / max($left, $right);

        return max(0.0, 1.0 - min(1.0, $difference));
    }
}
