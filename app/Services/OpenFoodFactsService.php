<?php

namespace App\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class OpenFoodFactsService
{
    protected string $baseUrl;
    protected string $beautyUrl;
    protected int $timeout;
    protected int $retryAttempts;
    protected bool $verifySsl;

    public function __construct()
    {
        $this->baseUrl = config('services.openfoodfacts.base_url', 'https://world.openfoodfacts.org/api/v2');
        $this->beautyUrl = config('services.openfoodfacts.beauty_url', 'https://world.openbeautyfacts.org/api/v2');
        $this->timeout = config('services.openfoodfacts.timeout', 10);
        $this->retryAttempts = config('services.openfoodfacts.retry_attempts', 3);
        $this->verifySsl = App::environment('production');
    }

    /**
     * Get product by barcode
     */
//    public function getProductByBarcode(string $barcode): ?array
//    {
//        // Check cache first
//        $cacheKey = "openfoodfacts_product_{$barcode}";
//
//        return Cache::remember($cacheKey, now()->addDays(7), function () use ($barcode) {
//            try {
//
//                $http = Http::timeout($this->timeout)
//                    ->retry($this->retryAttempts, 100);
//
//                if (!$this->verifySsl) {
//                    $http = $http->withoutVerifying();
//                    Log::info('SSL verification disabled for OpenFoodFacts API (development mode)');
//                }
//
//                $response = $http->get("{$this->baseUrl}/product/{$barcode}.json");
//
//
//                if ($response->successful()) {
//                    $data = $response->json();
//
//                    if ($data['status'] === 1) {
//                        return $this->transformProductData($data['product']);
//                    }
//                }
//
//                Log::info('Product not found on OpenFoodFacts', ['barcode' => $barcode]);
//                return null;
//
//            } catch (Exception $e) {
//                Log::error('OpenFoodFacts API error', [
//                    'barcode' => $barcode,
//                    'error' => $e->getMessage()
//                ]);
//
//                throw new Exception("Failed to fetch product from OpenFoodFacts: {$e->getMessage()}");
//            }
//        });
//    }

    /**
     * Get product by barcode - tries multiple product types
     */
    public function getProductByBarcode(string $barcode): ?array
    {
        // Check cache first
        $cacheKey = "openfoodfacts_product_{$barcode}";

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($barcode) {

            // Try food database first
            $foodProduct = $this->fetchFromEndpoint($this->baseUrl, $barcode);
            if ($foodProduct) {
                $foodProduct['product_type'] = 'food';
                return $foodProduct;
            }

            // Try beauty database
            $beautyProduct = $this->fetchFromEndpoint($this->beautyUrl, $barcode);
            if ($beautyProduct) {
                $beautyProduct['product_type'] = 'beauty';
                return $beautyProduct;
            }

            // Add more product types as needed:
            // - pet food: https://world.openpetfoodfacts.org
            // - product: https://world.openproductfacts.org

            Log::info('Product not found on any OpenFoodFacts database', ['barcode' => $barcode]);
            return null;
        });
    }

    /**
     * Fetch product from specific endpoint
     */
    protected function fetchFromEndpoint(string $endpoint, string $barcode): ?array
    {
        try {
            $http = Http::timeout($this->timeout)
                ->retry($this->retryAttempts, 100);

            if (!$this->verifySsl) {
                $http = $http->withoutVerifying();
            }

            $response = $http->get("{$endpoint}/product/{$barcode}.json");

            if ($response->successful()) {
                $data = $response->json();

                // Check if product was found (status 1 means found, 0 means not found)
                if (($data['status'] ?? 0) === 1) {
                    return $this->transformProductData($data['product'], $endpoint);
                } else {
                    // Log the verbose status for debugging
                    Log::info('OpenFoodFacts response', [
                        'barcode' => $barcode,
                        'status_verbose' => $data['status_verbose'] ?? 'unknown',
                        'endpoint' => $endpoint
                    ]);
                }
            }

            return null;

        } catch (Exception $e) {
            Log::error('OpenFoodFacts API error', [
                'barcode' => $barcode,
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);

            return null; // Return null instead of throwing, so we can try other endpoints
        }
    }

    /**
     * Search products
     */
    public function searchProducts(string $query, int $page = 1, int $pageSize = 20): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->retry($this->retryAttempts, 100)
                ->get("{$this->baseUrl}/search.json", [
                    'search_terms' => $query,
                    'page' => $page,
                    'page_size' => $pageSize,
                    'json' => 1,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'products' => array_map([$this, 'transformProductData'], $data['products'] ?? []),
                    'total' => $data['count'] ?? 0,
                    'page' => $data['page'] ?? $page,
                    'page_count' => $data['page_count'] ?? 0,
                ];
            }

            return ['products' => [], 'total' => 0, 'page' => $page, 'page_count' => 0];

        } catch (Exception $e) {
            Log::error('OpenFoodFacts search error', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);

            throw new Exception("Failed to search products: {$e->getMessage()}");
        }
    }

    /**
     * Transform OpenFoodFacts data to our format
     */
    protected function transformProductData(array $data, string $endpoint): array
    {
        // Determine product type from endpoint
        $productType = 'food';
        if (str_contains($endpoint, 'beauty')) {
            $productType = 'beauty';
        }

        return [
            'barcode' => $data['code'] ?? null,
            'name' => $data['product_name'] ?? $data['generic_name'] ?? 'Unknown Product',
            'brand' => $data['brands'] ?? null,
            'category_id' => $this->determineCategoryId($data, $productType),
            'image_url' => $data['image_url'] ?? $data['image_front_url'] ?? null,
            'source' => 'open_food_facts',
            'product_type' => $productType,
            'ingredients' => $this->extractIngredients($data),
            'nutrition' => $this->extractNutrition($data),
            'raw_data' => $data,
        ];
    }

    /**
     * Extract ingredients from product data
     */
    protected function extractIngredients(array $data): array
    {
        $ingredients = [];

        if (isset($data['ingredients']) && is_array($data['ingredients'])) {
            foreach ($data['ingredients'] as $ingredient) {
                $ingredients[] = [
                    'name' => $ingredient['text'] ?? $ingredient['id'] ?? 'Unknown',
                    'percent' => $ingredient['percent'] ?? null,
                    'vegan' => $ingredient['vegan'] ?? null,
                    'vegetarian' => $ingredient['vegetarian'] ?? null,
                ];
            }
        }

        return $ingredients;
    }

    /**
     * Extract nutrition data
     */
    protected function extractNutrition(array $data): array
    {
        $nutriments = $data['nutriments'] ?? [];

        return [
            'calories' => $nutriments['energy-kcal_100g'] ?? $nutriments['energy_100g'] ?? null,
            'fat' => $nutriments['fat_100g'] ?? null,
            'saturated_fat' => $nutriments['saturated-fat_100g'] ?? null,
            'carbohydrates' => $nutriments['carbohydrates_100g'] ?? null,
            'fiber' => $nutriments['fiber_100g'] ?? null,
            'sugars' => $nutriments['sugars_100g'] ?? null,
            'protein' => $nutriments['proteins_100g'] ?? null,
            'salt' => $nutriments['salt_100g'] ?? null,
            'sodium' => $nutriments['sodium_100g'] ?? null,
        ];
    }

    /**
     * Determine category ID based on product data
     */
    protected function determineCategoryId(array $data, string $productType): ?int
    {
        if ($productType === 'beauty') {
            // Beauty/cosmetic categories (100+ range)
            $categories = $data['categories'] ?? '';

            if (str_contains($categories, 'face')) return 101;
            if (str_contains($categories, 'body')) return 102;
            if (str_contains($categories, 'hair')) return 103;
            if (str_contains($categories, 'makeup')) return 104;

            return 100; // Default beauty category
        } else {
            // Food categories (1-99 range)
            $categories = $data['categories'] ?? '';
            $novaGroup = $data['nova_group'] ?? null;

            if ($novaGroup) {
                // Ultra-processed foods
                if ($novaGroup == 4) return 4;
                // Processed foods
                if ($novaGroup == 3) return 3;
                // Processed culinary ingredients
                if ($novaGroup == 2) return 2;
                // Unprocessed/minimally processed
                if ($novaGroup == 1) return 1;
            }

            return 1; // Default food category
        }
    }
}
