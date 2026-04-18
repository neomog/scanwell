# Contribution And Moderation API Contract

## Summary

Yes, some request and response shapes changed.

The important point is that the top-level wrappers are still mostly the same:

- reads still return `success` + `data`
- writes still return `success` + `message` + `data`

The main contract changes are around contribution behavior and richer product payloads.

## What Changed

### 1. New product contributions no longer create products immediately

Before this implementation, an `add` contribution could create a product right away.

Now:

- `POST /api/v1/products/{barcode}/contribute`
- with `change_type: add`
- creates a `pending` contribution only
- the product is created only after admin approval

That is the biggest behavior change.

### 2. Contribution request body is now richer

The contribution endpoint now accepts a full structured payload, not just simple field patches.

Supported fields now include:

- `product_name` or `name`
- `brand`
- `category_id`
- `category_name`
- `product_family`
- `ingredients_text`
- `ingredients`
- `nutrition`
- `additives`
- `allergens`
- `region_availability`
- `barcodes`
- `images`
- `image_urls`
- `image_files`

Notes:

- `new_value` is still supported for simple field-level corrections
- `field_name + new_value` is mapped into the canonical product field
- `old_value` is no longer part of the implemented request contract and is not used by the new flow

### 3. Product responses are richer

`ProductResource` now returns additional fields:

- `barcodes`
- `images`
- `category_name`
- `product_family`
- `ingredients_text`
- `additives`
- `allergens`
- `region_availability`

`image_url` now represents the resolved primary image URL.

### 4. Missing approved products can now return pending contribution info

These endpoints can now return pending moderation details when the barcode is not yet approved:

- `GET /api/v1/products/barcode/{barcode}`
- `GET /api/v1/products/search`
- `POST /api/v1/scan`

So the "not found" response is now richer.

### 5. New endpoints were added

- `PUT /api/v1/my-contributions/{id}`
- `GET /api/v1/contributions/leaderboard`
- `PUT /api/v1/admin/contributions/{id}`
- `POST /api/v1/admin/contributions/{id}/flag`

### 6. Admin product create/update accepts richer payloads

Admin product management endpoints now also accept:

- multiple images
- multiple barcodes
- additives
- allergens
- region availability
- structured ingredients and nutrition

## Compatibility Notes

### Still mostly compatible

- `POST /api/v1/scan` success response is still `success + message + data`
- `GET /api/v1/products/barcode/{barcode}` success response is still `success + data`
- existing clients that only read `barcode`, `name`, `brand`, `score`, and `scores` should continue to work

### Behavior that clients must update for

- do not assume `change_type=add` returns a created product
- do not assume a contributed barcode becomes visible immediately to scans
- handle `pending_contribution` in barcode lookup and scan 404 responses

## Base URL

Examples below assume:

```text
https://scanwell.ohiare.com/api
```

Use your real host if different.

## Auth Header

Protected endpoints require:

```text
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

## Sample 1: User submits a missing product

### cURL

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/products/9900012345678/contribute \
  --header "Accept: application/json" \
  --header "Authorization: Bearer YOUR_TOKEN" \
  --header "Content-Type: application/json" \
  --data '{
    "change_type": "add",
    "product_name": "Community Juice",
    "brand": "Scanwell Labs",
    "product_family": "food",
    "category_id": 15,
    "category_name": "Juices",
    "ingredients_text": "Water, Apple Juice Concentrate, Vitamin C",
    "ingredients": [
      { "name": "Water" },
      { "name": "Apple Juice Concentrate" },
      { "name": "Vitamin C", "is_additive": true }
    ],
    "nutrition": {
      "calories": 80,
      "sugars": 12,
      "carbohydrates": 18,
      "protein": 0
    },
    "additives": ["Vitamin C"],
    "allergens": [],
    "region_availability": ["NG", "GH"],
    "image_urls": [
      "https://cdn.example.com/products/community-juice-front.jpg"
    ],
    "reason": "This product is missing from the catalogue and should be reviewed."
  }'
```

### Sample Response

```json
{
  "success": true,
  "message": "Contribution submitted successfully. It will become a product only after admin approval.",
  "data": {
    "contribution": {
      "id": "7db3f5b0-f0c5-4c2c-9d4f-4b4e5434f2b1",
      "user": {
        "id": "0be7f55a-d4de-4635-915e-bbdb4d4d41ad",
        "name": "Mayor"
      },
      "product": null,
      "barcode": "9900012345678",
      "product_name": "Community Juice",
      "change_type": "add",
      "field_name": null,
      "old_data": null,
      "new_data": {
        "barcode": "9900012345678",
        "name": "Community Juice",
        "brand": "Scanwell Labs",
        "category_id": 15,
        "category_name": "Juices",
        "product_family": "food",
        "ingredients_text": "Water, Apple Juice Concentrate, Vitamin C",
        "additives": ["Vitamin C"],
        "allergens": [],
        "region_availability": ["NG", "GH"],
        "images": [
          {
            "url": "https://cdn.example.com/products/community-juice-front.jpg",
            "disk": null,
            "path": null,
            "source": "manual",
            "is_primary": true,
            "sort_order": 0
          }
        ],
        "ingredients": [
          { "name": "Water", "percentage": null, "origin": null, "is_additive": false },
          { "name": "Apple Juice Concentrate", "percentage": null, "origin": null, "is_additive": false },
          { "name": "Vitamin C", "percentage": null, "origin": null, "is_additive": true }
        ],
        "nutrition": {
          "calories": 80,
          "sugars": 12,
          "carbohydrates": 18,
          "protein": 0
        }
      },
      "moderated_data": null,
      "reason": "This product is missing from the catalogue and should be reviewed.",
      "evidence": null,
      "status": "pending",
      "review_notes": null,
      "flag_reason": null,
      "flagged_at": null,
      "reputation_points_awarded": 0,
      "meta": {
        "submitted_via": "api"
      },
      "reviewed_at": null,
      "created_at": "2026-04-18T10:22:00.000000Z",
      "updated_at": "2026-04-18T10:22:00.000000Z"
    }
  }
}
```

## Sample 2: User corrects an existing product field

### cURL

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/products/8800012345678/contribute \
  --header "Accept: application/json" \
  --header "Authorization: Bearer YOUR_TOKEN" \
  --header "Content-Type: application/json" \
  --data '{
    "change_type": "correct",
    "field_name": "brand",
    "new_value": "Fixed Brand",
    "reason": "The brand printed on the package is different from the current record."
  }'
```

### Sample Response

```json
{
  "success": true,
  "message": "Contribution submitted successfully. It will become a product only after admin approval.",
  "data": {
    "contribution": {
      "id": "dc805533-0cd7-4d66-a81c-e45a7cfdcbbe",
      "barcode": "8800012345678",
      "product_name": "Original Snack",
      "change_type": "correct",
      "field_name": "brand",
      "status": "pending",
      "reason": "The brand printed on the package is different from the current record.",
      "new_data": {
        "barcode": "8800012345678",
        "name": "Original Snack",
        "brand": "Fixed Brand"
      }
    }
  }
}
```

## Sample 3: Scan barcode before admin approval

### cURL

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/scan \
  --header "Accept: application/json" \
  --header "Authorization: Bearer YOUR_TOKEN" \
  --header "Content-Type: application/json" \
  --data '{
    "barcode": "9900012345678"
  }'
```

### Sample Response

```json
{
  "success": false,
  "message": "Product is not in the catalogue yet. A community submission is pending review.",
  "data": {
    "scan": {
      "id": "f79dce02-18bf-4620-88bb-f49fbf3374bc",
      "barcode": "9900012345678",
      "scan_timestamp": "2026-04-18T10:28:00.000000Z",
      "device_type": "PostmanRuntime/7.44.1",
      "status": "failed",
      "scan_metadata": {
        "source": "mobile_app",
        "error": "Product not found with barcode: 9900012345678"
      },
      "product": null,
      "created_at": "2026-04-18T10:28:00.000000Z"
    },
    "product": null,
    "pending_contribution": {
      "id": "7db3f5b0-f0c5-4c2c-9d4f-4b4e5434f2b1",
      "barcode": "9900012345678",
      "product_name": "Community Juice",
      "change_type": "add",
      "status": "pending",
      "reason": "This product is missing from the catalogue and should be reviewed."
    }
  }
}
```

## Sample 4: Admin approves a contribution

### cURL

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/admin/contributions/7db3f5b0-f0c5-4c2c-9d4f-4b4e5434f2b1/approve \
  --header "Accept: application/json" \
  --header "Authorization: Bearer ADMIN_TOKEN" \
  --header "Content-Type: application/json" \
  --data '{
    "notes": "Verified against packaging.",
    "category_name": "Juices",
    "region_availability": ["NG", "GH", "KE"]
  }'
```

### Sample Response

```json
{
  "success": true,
  "message": "Contribution approved successfully",
  "data": {
    "contribution": {
      "id": "7db3f5b0-f0c5-4c2c-9d4f-4b4e5434f2b1",
      "barcode": "9900012345678",
      "product_name": "Community Juice",
      "change_type": "add",
      "status": "approved",
      "review_notes": "Verified against packaging.",
      "reputation_points_awarded": 25
    },
    "product": {
      "id": "33e2c515-c245-42a6-9343-a7dcf50c4d18",
      "barcode": "9900012345678",
      "barcodes": [
        {
          "id": "4904f7d7-2ad1-4e06-bad3-277c7b8b07dd",
          "barcode": "9900012345678",
          "label": "primary",
          "is_primary": true
        }
      ],
      "name": "Community Juice",
      "brand": "Scanwell Labs",
      "image_url": "https://cdn.example.com/products/community-juice-front.jpg",
      "images": [
        {
          "id": "ce8863a1-e06d-4d55-b523-252f00c91b78",
          "url": "https://cdn.example.com/products/community-juice-front.jpg",
          "source": "manual",
          "is_primary": true,
          "sort_order": 0
        }
      ],
      "category_id": 15,
      "category_name": "Juices",
      "product_family": "food",
      "source": "community",
      "ingredients_text": "Water, Apple Juice Concentrate, Vitamin C",
      "additives": ["Vitamin C"],
      "allergens": [],
      "region_availability": ["NG", "GH", "KE"],
      "score": 62,
      "score_grade": "B",
      "scores": {
        "food": {
          "overall_score": 62,
          "nutrition_score": 58,
          "ingredient_score": 72,
          "additive_score": 55,
          "processing_score": 60
        },
        "cosmetic": null
      }
    },
    "points_awarded": 25
  }
}
```

## Sample 5: Public barcode lookup after approval

### cURL

```bash
curl --request GET \
  --url https://scanwell.ohiare.com/api/v1/products/barcode/9900012345678 \
  --header "Accept: application/json"
```

### Sample Response

```json
{
  "success": true,
  "data": {
    "id": "33e2c515-c245-42a6-9343-a7dcf50c4d18",
    "barcode": "9900012345678",
    "barcodes": [
      {
        "id": "4904f7d7-2ad1-4e06-bad3-277c7b8b07dd",
        "barcode": "9900012345678",
        "label": "primary",
        "is_primary": true
      }
    ],
    "name": "Community Juice",
    "brand": "Scanwell Labs",
    "image_url": "https://cdn.example.com/products/community-juice-front.jpg",
    "images": [
      {
        "id": "ce8863a1-e06d-4d55-b523-252f00c91b78",
        "url": "https://cdn.example.com/products/community-juice-front.jpg",
        "source": "manual",
        "is_primary": true,
        "sort_order": 0
      }
    ],
    "category_id": 15,
    "category_name": "Juices",
    "product_family": "food",
    "source": "community",
    "ingredients_text": "Water, Apple Juice Concentrate, Vitamin C",
    "additives": ["Vitamin C"],
    "allergens": [],
    "region_availability": ["NG", "GH", "KE"],
    "ingredients": [
      {
        "id": "11d74cd9-fd86-4d9f-84e7-7b04042712b7",
        "name": "Water",
        "category": "other",
        "risk_level": "low"
      }
    ],
    "nutrition": {
      "calories": 80,
      "carbohydrates": 18,
      "sugars": 12,
      "protein": 0,
      "health_score": 80
    },
    "scores": {
      "food": {
        "overall_score": 62,
        "nutrition_score": 58,
        "ingredient_score": 72,
        "additive_score": 55,
        "processing_score": 60
      },
      "cosmetic": null
    },
    "alternatives": [],
    "score": 62,
    "score_grade": "B",
    "created_at": "2026-04-18T10:33:00.000000Z",
    "updated_at": "2026-04-18T10:33:00.000000Z"
  },
  "personalized_warnings": []
}
```

## Sample 6: Admin creates a product directly

### cURL

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/admin/products \
  --header "Accept: application/json" \
  --header "Authorization: Bearer ADMIN_TOKEN" \
  --header "Content-Type: application/json" \
  --data '{
    "barcode": "7000001112223",
    "name": "Admin Added Lotion",
    "brand": "Scanwell Care",
    "product_family": "cosmetic",
    "category_id": 110,
    "category_name": "Face Care",
    "ingredients_text": "Water, Glycerin, Fragrance",
    "ingredients": [
      { "name": "Water" },
      { "name": "Glycerin" },
      { "name": "Fragrance" }
    ],
    "allergens": ["Fragrance"],
    "region_availability": ["NG"],
    "image_urls": [
      "https://cdn.example.com/products/admin-lotion-front.jpg"
    ]
  }'
```

### Sample Response

```json
{
  "success": true,
  "message": "Product created successfully",
  "data": {
    "id": "0940ef1d-f43c-4a4b-a929-c9e7f2e3e288",
    "barcode": "7000001112223",
    "name": "Admin Added Lotion",
    "brand": "Scanwell Care",
    "product_family": "cosmetic",
    "category_id": 110,
    "category_name": "Face Care",
    "source": "admin_manual",
    "allergens": ["Fragrance"],
    "region_availability": ["NG"],
    "score": 71,
    "score_grade": "B+"
  }
}
```

## Postman Note

You can import any of the `curl` blocks directly into Postman using:

- Postman
- Import
- Raw text
- paste the `curl`

## File Upload Note

If you want to send `image_files`, use `multipart/form-data` instead of raw JSON.

Example fields for multipart:

- `change_type=add`
- `product_name=Community Juice`
- `reason=...`
- `image_files[]=@front.jpg`
- `image_files[]=@ingredients.jpg`

## Recommended Client Handling

For mobile and frontend clients, update the app to:

1. Treat `add` contributions as moderation requests, not instant products.
2. Handle `pending_contribution` on scan and barcode lookup failures.
3. Read the richer `ProductResource` fields when available.
4. Use leaderboard and contribution status views for contributor UX.
