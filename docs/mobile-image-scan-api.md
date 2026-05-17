# Mobile Image Scan API

## Overview

This document is for the mobile developer integrating image-based product scanning.

The image scan API lets the app:

- upload a product photo
- let the backend identify the product from the image
- reuse the same product analysis and score response shape as barcode scan

The backend tries identification in this order:

1. barcode extracted from the image
2. barcode hint sent by the app
3. OCR text and product label matching

If a product is identified, the backend returns the same scan result structure already used for barcode scanning.

## Endpoint

`POST /api/v1/scan/image`

Base URL example:

- `https://scanwell.ohiare.com`

## Authentication

This endpoint requires:

```bash
Authorization: Bearer YOUR_ACCESS_TOKEN
Accept: application/json
Content-Type: multipart/form-data
```

## Request Fields

Required:

- `image`

Optional:

- `barcode_hint`
- `product_name`
- `brand`
- `extracted_text`
- `latitude`
- `longitude`

### Field Notes

- `image`: product image file
- `barcode_hint`: 8-13 digit barcode if the app already detected one locally
- `product_name`: product name guessed from on-device OCR
- `brand`: brand guessed from on-device OCR
- `extracted_text`: raw OCR text from the label
- `latitude`: optional scan latitude
- `longitude`: optional scan longitude

## Sample Request

Minimal request:

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/scan/image \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json" \
  --form "image=@/path/to/product.jpg"
```

Request with app-side hints:

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/scan/image \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json" \
  --form "image=@/path/to/product.jpg" \
  --form "barcode_hint=12345678" \
  --form "product_name=Kirkland Purified Water" \
  --form "brand=Kirkland" \
  --form "extracted_text=Kirkland Purified Water 12345678" \
  --form "latitude=6.5244" \
  --form "longitude=3.3792"
```

## Success Response

The top-level response format matches the existing scan API.

Sample response:

```json
{
  "success": true,
  "message": "Product scanned successfully",
  "data": {
    "scan": {
      "id": "a9c3c4d2-5f87-4fc8-8d35-0f95db7d2abc",
      "user_id": "34bc3c65-2e5b-4a95-bb15-227f6b2f9001",
      "product_id": "f7e2e5a7-c8d6-4dbd-bf76-1b7f45a41840",
      "barcode": "12345678",
      "scan_timestamp": "2026-05-17T15:20:00.000000Z",
      "device_type": "okhttp/4.12.0",
      "status": "completed",
      "scan_metadata": {
        "ip": "197.210.0.10",
        "source": "mobile_app",
        "scan_mode": "image",
        "latitude": 6.5244,
        "longitude": 3.3792,
        "matched_provider": "open_food_facts",
        "product_family": "food",
        "confidence": 91,
        "matched_by": "image_barcode",
        "analysis_source": "openai_vision",
        "provider_lookup": {
          "attempts": [],
          "attempted_provider_keys": [],
          "attempts_count": 0,
          "resolved": true
        },
        "image_scan": {
          "stored_image": {
            "disk": "public",
            "path": "scan-inputs/abc123.jpg",
            "url": "https://scanwell.ohiare.com/storage/scan-inputs/abc123.jpg",
            "original_name": "product.jpg",
            "mime_type": "image/jpeg",
            "size": 248193
          },
          "signals": {
            "barcode": "12345678",
            "product_name": "Kirkland Purified Water",
            "brand": "Kirkland",
            "extracted_text": "Kirkland Purified Water 12345678",
            "category_hint": "food",
            "confidence": 91,
            "front_label_visible": true,
            "barcode_visible": true,
            "nutrition_panel_visible": false,
            "ingredients_visible": false
          }
        }
      },
      "created_at": "2026-05-17T15:20:00.000000Z",
      "updated_at": "2026-05-17T15:20:02.000000Z"
    },
    "product": {
      "id": "f7e2e5a7-c8d6-4dbd-bf76-1b7f45a41840",
      "barcode": "12345678",
      "barcodes": [],
      "name": "Kirkland Purified Water",
      "brand": "Kirkland",
      "image_url": "https://example.com/product.jpg",
      "images": [],
      "category_id": 1,
      "category_name": "Waters",
      "product_family": "food",
      "source": "open_food_facts",
      "ingredients_text": "Water",
      "additives": [],
      "allergens": [],
      "region_availability": [],
      "ingredients": [],
      "nutrition": {
        "calories": 0,
        "fat": 0,
        "saturated_fat": 0,
        "carbohydrates": 0,
        "fiber": 0,
        "sugars": 0,
        "protein": 0,
        "sodium": 0,
        "serving_size": null
      },
      "scores": {
        "food": {
          "overall_score": 89,
          "nutrition_score": 95,
          "ingredient_score": 90,
          "additive_score": 90,
          "processing_score": 85,
          "grade": "A",
          "color": "green",
          "nova_group": null,
          "nutriscore_grade": null,
          "score_breakdown": {
            "nutrition": 95,
            "ingredient": 90,
            "additive": 90,
            "processing": 85,
            "packaging": 60,
            "confidence": 91,
            "completeness": 70
          },
          "explanation_text": "This bottled water scored 95 for nutrition and 60 for packaging. Key concern: Packaging reduces the product score.",
          "warnings": [
            "Plastic bottled water is capped below a perfect score because packaging impact matters."
          ],
          "benefits": [
            "Hydration-friendly product profile."
          ],
          "calculated_at": "2026-05-17T15:20:02.000000Z"
        },
        "cosmetic": null
      },
      "alternatives": [],
      "score": 89,
      "score_grade": "A",
      "created_at": "2026-05-17T15:20:02.000000Z",
      "updated_at": "2026-05-17T15:20:02.000000Z"
    },
    "personalized_warnings": [],
    "alternatives": [],
    "score_interpretation": {
      "grade": "Excellent",
      "description": "This product scores strongly with limited flagged concerns.",
      "color": "green"
    },
    "remaining_monthly_scans": 47
  }
}
```

## 422 Response

If the backend cannot confidently identify the product from the image:

```json
{
  "success": false,
  "message": "We could not confidently identify this product from the uploaded image. Include a barcode hint or OCR text for now.",
  "data": {
    "scan": {
      "id": "a9c3c4d2-5f87-4fc8-8d35-0f95db7d2abc",
      "status": "failed"
    },
    "product": null
  }
}
```

## 404 Response

If a barcode was extracted but no catalog product was found:

```json
{
  "success": false,
  "message": "Product not found",
  "data": {
    "scan": {
      "id": "a9c3c4d2-5f87-4fc8-8d35-0f95db7d2abc",
      "status": "failed"
    },
    "product": null,
    "pending_contribution": null
  }
}
```

## Mobile Integration Notes

- Reuse the existing scan result UI because the response shape matches barcode scan.
- For analytics or debugging, mobile can read:
  - `data.scan.scan_metadata.scan_mode`
  - `data.scan.scan_metadata.matched_by`
  - `data.scan.scan_metadata.analysis_source`
  - `data.scan.scan_metadata.image_scan.signals`
- Best accuracy comes from sending:
  - image
  - barcode hint if available
  - extracted OCR text if available

## Result Sources

The backend may identify the product using:

- `image_barcode`
- `image_barcode_hint`
- `image_text_match`

The `analysis_source` field may show values such as:

- `openai_vision`
- `barcode_hint`
- `openai_vision_local_catalog_match`
- `local_catalog_text_match`

## Failure Handling Recommendation

Recommended client behavior:

1. If the response is `200`, show the normal scan result screen.
2. If the response is `422`, ask the user to retake the photo or crop closer to the label.
3. If the response is `404`, offer manual contribution if that flow exists in the app.
