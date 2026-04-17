# Scan Engine

## Goal

Keep the existing mobile API contract intact while making barcode resolution and product scoring more reliable.

The request and response shapes for:

- `POST /api/v1/scan`
- `GET /api/v1/products/barcode/{barcode}`
- `GET /api/v1/products/search`

remain the same at the top level. The redesign only changes how the backend finds, classifies, and scores products internally.

## Why the old results were wrong

### Plastic bottled water showing `100`

There were three main causes:

1. The scan controller treated missing scores as `100`.
2. Product family detection could misclassify non-food and beauty products.
3. Sparse vendor data was being scored too optimistically.

That combination meant an unscored or weakly-scored product could still be interpreted as "Excellent".

### Wisconsin bottled water showing mayonnaise

That usually happens when the system accepts a weak fallback result instead of an exact barcode match, or when search ranking is too loose around product names.

The new engine reduces that risk by:

- requiring exact barcode matches from vendors
- rejecting vendor responses whose returned barcode does not match the scanned barcode
- classifying products before scoring them
- adding a local barcode-first search fallback instead of trusting loose matches

## New flow

1. `ScanController` creates the scan record.
2. `ProductAnalysisService` checks the local `products` table first.
3. If the product is missing or stale, `ProductCatalogService` asks configured providers for an exact barcode match.
4. `OpenFoodFactsService` checks multiple databases:
   - Open Food Facts
   - Open Beauty Facts
   - Open Product Facts
   - Open Pet Food Facts
5. `ProductFamilyResolver` decides the product family:
   - `food`
   - `cosmetic`
   - `pet_food`
   - `household`
   - `general`
6. The product is stored with extra scan metadata under `raw_data._scanwell`.
7. `ScoreCalculationService` applies conservative scoring rules:
   - no default perfect scores
   - sparse data caps
   - packaging-aware scoring
   - bottled-water plastic penalty
   - unsupported categories return `Unknown` instead of fake high scores
8. The API response is returned in the same shape the mobile app already expects.

## Stored metadata

Each externally resolved product now keeps internal scan metadata in `products.raw_data._scanwell`:

- `provider`
- `source`
- `product_family`
- `confidence`
- `completeness`
- `matched_by`
- `packaging`
- `warnings`
- `resolved_at`

This is useful for debugging bad matches without changing the mobile response format.

## Scoring rules

### Food

Food scores now consider:

- nutrition
- ingredient quality
- additive risk
- processing
- packaging

Important safeguards:

- no food product gets a default `100`
- sparse food data is capped
- plastic bottled water is capped below a perfect score
- packaged products are capped below the configured perfect-score limit

### Cosmetics

Cosmetics now:

- get cosmetic scoring only
- no longer fall through into food scoring
- receive conservative scores when the ingredient list is missing

### Household and general products

These categories can be found and returned, but they are not forced into fake food or cosmetic scores.

The API still returns `score_interpretation`, but it becomes:

- `grade: Unknown`
- `color: gray`

when the category is not supported or trusted data is incomplete.

## Vendor architecture

Providers now implement `App\Contracts\ProductCatalogProvider`.

Current provider list lives in [config/scanning.php](/C:/Users/mayor/Documents/Ohiare/scanwell/home/ohiamczl/scanwell.ohiare.com/config/scanning.php:1).

### Add another vendor

1. Create a new provider class that implements `ProductCatalogProvider`.
2. Normalize the vendor payload into this shape:

```php
[
    'barcode' => '...',
    'name' => '...',
    'brand' => '...',
    'category_id' => null,
    'image_url' => '...',
    'source' => 'vendor_key',
    'product_type' => 'food|cosmetic|pet_food|household|general',
    'ingredients' => [...],
    'nutrition' => [...],
    'packaging' => [...],
    'warnings' => [...],
    'raw_data' => [...],
]
```

3. Register the provider class in `config/scanning.php`.
4. Keep the controller responses unchanged.

## Files to know

- [app/Services/ProductCatalogService.php](/C:/Users/mayor/Documents/Ohiare/scanwell/home/ohiamczl/scanwell.ohiare.com/app/Services/ProductCatalogService.php:1)
- [app/Services/ProductFamilyResolver.php](/C:/Users/mayor/Documents/Ohiare/scanwell/home/ohiamczl/scanwell.ohiare.com/app/Services/ProductFamilyResolver.php:1)
- [app/Services/OpenFoodFactsService.php](/C:/Users/mayor/Documents/Ohiare/scanwell/home/ohiamczl/scanwell.ohiare.com/app/Services/OpenFoodFactsService.php:1)
- [app/Services/ProductAnalysisService.php](/C:/Users/mayor/Documents/Ohiare/scanwell/home/ohiamczl/scanwell.ohiare.com/app/Services/ProductAnalysisService.php:1)
- [app/Services/ScoreCalculationService.php](/C:/Users/mayor/Documents/Ohiare/scanwell/home/ohiamczl/scanwell.ohiare.com/app/Services/ScoreCalculationService.php:1)
- [app/Http/Controllers/ScanController.php](/C:/Users/mayor/Documents/Ohiare/scanwell/home/ohiamczl/scanwell.ohiare.com/app/Http/Controllers/ScanController.php:1)

## Verification

Database-free unit coverage was added for:

- family resolution
- bottled-water packaging caps
- conservative cosmetic scoring with missing ingredients

Feature tests were also added for full API behavior, but they require the SQLite PDO driver in the environment to run locally.
