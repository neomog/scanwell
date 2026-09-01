# Scanning Providers

This document complements the multi-provider scan engine and the super-admin provider dashboard.

## Current provider lineup

- `open_facts`
  - Driver: `App\Services\OpenFoodFactsService`
  - Type: free
  - Covers: `food`, `cosmetic`, `pet_food`, `household`, `general`

- `edamam`
  - Driver: `App\Services\EdamamFoodDatabaseService`
  - Type: paid
  - Covers: `food`

- `gs1_us`
  - Driver: `App\Services\Gs1UsProductService`
  - Type: enterprise paid
  - Covers: `food`, `cosmetic`, `pet_food`, `household`, `general`

- `openai_barcode_web`
  - Driver: `App\\Services\\OpenAiBarcodeWebSearchService`
  - Type: fallback
  - Covers: `food`, `cosmetic`, `pet_food`, `household`, `general`
  - Runs only after deterministic catalog providers return no acceptable exact-barcode candidate.
  - Requires an exact barcode-supported web result; otherwise the scan keeps the existing not-found flow.

## Admin workflow

Open `/admin/scanning/providers` as a super admin.

For each provider you can:

- enable or disable it
- move it up or down using `priority`
- restrict product families
- tune `timeout`, `retries`, and `cache_ttl_minutes`
- add runtime `settings` JSON
- add encrypted `credentials` JSON
- run a provider-specific `Sync Products` action when the driver supports imports

## Credentials JSON examples

## One-click provider imports

The admin dashboard can show a `Sync Products` button per provider.

Right now, that button is available only for providers whose driver supports catalog search imports. The first implementation is intended for `open_facts`.

Add this block to the provider `settings` JSON:

```json
{
  "import": {
    "query": "snacks",
    "page_size": 20,
    "max_pages": 2
  }
}
```

Notes:

- `query` is required
- `page_size` defaults to `20`
- `max_pages` defaults to `1`
- the import runs in the queue, so make sure a worker is running

### Edamam

```json
{
  "app_id": "your_edamam_app_id",
  "app_key": "your_edamam_app_key"
}
```

### GS1 US

```json
{
  "api_key": "your_gs1_api_key",
  "account_id": "optional_account_id"
}
```

## Settings JSON examples

### Edamam

```json
{
  "base_url": "https://api.edamam.com/api/food-database/v2/parser",
  "category": "packaged-foods",
  "nutrition_type": "cooking"
}
```

### GS1 US

The GS1 US driver is intentionally endpoint-configurable because the exact request URL depends on the subscribed API operation in the GS1 US Developer Portal.

```json
{
  "base_url": "https://your-gs1-endpoint-from-developer-portal",
  "http_method": "GET",
  "barcode_field": "gtin",
  "barcode_path": "gtin"
}
```

## Recommended priority

Suggested production order:

1. `gs1_us`
2. `open_facts`
3. `edamam`

That order gives you:

- trusted identity verification first
- free high-signal food and cosmetic enrichment second
- paid food enrichment third
- broad commercial metadata fallback after that
