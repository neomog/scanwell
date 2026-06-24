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
        $sourceHash = $this->differenceHash($imageBinary);

        if ($sourceHash === null) {
            return [];
        }

        $scores = [];

        foreach ($products as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $best = $this->bestSimilarityForProduct($sourceHash, $product);

            if ($best === null) {
                continue;
            }

            $scores[$product->id] = $best;
        }

        return $scores;
    }

    protected function bestSimilarityForProduct(string $sourceHash, Product $product): ?array
    {
        $images = $product->relationLoaded('images')
            ? $product->images
            : $product->images()->get();

        $best = null;

        foreach ($images as $image) {
            $candidateBinary = $this->loadProductImageBinary($image->disk, $image->path, $image->url);

            if ($candidateBinary === null) {
                continue;
            }

            $candidateHash = $this->differenceHashCached($candidateBinary, $image->disk, $image->path, $image->url);

            if ($candidateHash === null) {
                continue;
            }

            $similarity = $this->hashSimilarity($sourceHash, $candidateHash);

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

    protected function differenceHashCached(string $imageBinary, ?string $disk, ?string $path, ?string $url): ?string
    {
        $cacheKey = 'scanwell:image-hash:' . sha1(($disk ?? '') . '|' . ($path ?? '') . '|' . ($url ?? '') . '|' . strlen($imageBinary));

        return Cache::remember($cacheKey, now()->addDays(7), fn () => $this->differenceHash($imageBinary));
    }

    protected function differenceHash(string $imageBinary): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring($imageBinary);

        if ($image === false) {
            return null;
        }

        $thumb = imagecreatetruecolor(9, 8);

        if ($thumb === false) {
            imagedestroy($image);

            return null;
        }

        imagecopyresampled($thumb, $image, 0, 0, 0, 0, 9, 8, imagesx($image), imagesy($image));

        $bits = '';

        for ($y = 0; $y < 8; $y++) {
            $row = [];

            for ($x = 0; $x < 9; $x++) {
                $rgb = imagecolorat($thumb, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $row[] = (int) round(($r * 0.299) + ($g * 0.587) + ($b * 0.114));
            }

            for ($x = 0; $x < 8; $x++) {
                $bits .= $row[$x] > $row[$x + 1] ? '1' : '0';
            }
        }

        imagedestroy($thumb);
        imagedestroy($image);

        return $bits === '' ? null : $bits;
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
}
