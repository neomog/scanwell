# Mobile Image Scan API

## Overview

This document is for the mobile developer integrating image-based product scanning.

The image scan API lets the app:

- upload a product photo
- let the backend identify the product from the image
- reuse the same product analysis and score response shape as barcode scan

The backend tries identification in this order:

1. exact barcode match from app hint or OCR
2. normalized `brand + product_name` match against the local catalog
3. broader OCR text match against known catalog aliases

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

The backend now performs OCR itself with Google Cloud Vision `DOCUMENT_TEXT_DETECTION`, so the optional app-side hints are best treated as helpful overrides rather than a requirement.

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
        "analysis_source": "google_cloud_vision",
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
          "ocr": {
            "extracted_text": "Kirkland Purified Water 12345678",
            "product_name": "Purified Water",
            "brand": "Kirkland",
            "barcode_hint": "12345678",
            "confidence": 0.91,
            "provider": "google_cloud_vision",
            "mode": "DOCUMENT_TEXT_DETECTION"
          },
          "signals": {
            "barcode": "12345678",
            "barcode_source": "ocr_barcode_hint",
            "product_name": "Kirkland Purified Water",
            "normalized_product_name": "kirkland purified water",
            "brand": "Kirkland",
            "normalized_brand": "kirkland",
            "extracted_text": "Kirkland Purified Water 12345678",
            "normalized_extracted_text": "kirkland purified water 12345678",
            "confidence": 0.91,
            "analysis_source": "google_cloud_vision",
            "ocr_provider": "google_cloud_vision",
            "ocr_mode": "DOCUMENT_TEXT_DETECTION",
            "ocr_output": {
              "extracted_text": "Kirkland Purified Water 12345678",
              "product_name": "Purified Water",
              "brand": "Kirkland",
              "barcode_hint": "12345678",
              "confidence": 0.91,
              "provider": "google_cloud_vision",
              "mode": "DOCUMENT_TEXT_DETECTION"
            }
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

## Failure Responses

If the backend cannot read enough product information from the image:

```json
{
  "success": false,
  "message": "We could not read enough product information from the image. Please retake the photo with the label or barcode clearly visible.",
  "error_code": "ocr_unreadable",
  "data": {
    "scan": {
      "id": "a9c3c4d2-5f87-4fc8-8d35-0f95db7d2abc",
      "status": "failed"
    },
    "product": null,
    "match_context": {
      "barcode": null,
      "barcode_source": null,
      "product_name": null,
      "brand": null,
      "extracted_text": null,
      "ocr_provider": "google_cloud_vision",
      "ocr_mode": "DOCUMENT_TEXT_DETECTION",
      "analysis_source": "google_cloud_vision"
    }
  }
}
```

If a barcode was extracted but no catalog product was found:

```json
{
  "success": false,
  "message": "We found a barcode in the image, but this product is not in our catalog yet.",
  "error_code": "barcode_not_found",
  "data": {
    "scan": {
      "id": "a9c3c4d2-5f87-4fc8-8d35-0f95db7d2abc",
      "status": "failed"
    },
    "product": null,
    "match_context": {
      "barcode": "12345678",
      "barcode_source": "ocr_barcode_hint",
      "product_name": "Purified Water",
      "brand": "Kirkland",
      "extracted_text": "Kirkland Purified Water 12345678",
      "ocr_provider": "google_cloud_vision",
      "ocr_mode": "DOCUMENT_TEXT_DETECTION",
      "analysis_source": "google_cloud_vision"
    }
  }
}
```

If OCR text was extracted but it did not match any product in the catalog:

```json
{
  "success": false,
  "message": "We extracted product label text from the image, but it does not match any product in our catalog.",
  "error_code": "ocr_text_no_catalog_match",
  "data": {
    "scan": {
      "id": "a9c3c4d2-5f87-4fc8-8d35-0f95db7d2abc",
      "status": "failed"
    },
    "product": null,
    "match_context": {
      "barcode": null,
      "barcode_source": null,
      "product_name": "Purified Water",
      "brand": "Kirkland",
      "extracted_text": "Kirkland Purified Water 12345678",
      "ocr_provider": "google_cloud_vision",
      "ocr_mode": "DOCUMENT_TEXT_DETECTION",
      "analysis_source": "google_cloud_vision"
    }
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
- `image_brand_product_match`
- `image_alias_text_match`

The `analysis_source` field may show values such as:

- `google_cloud_vision`
- `client_hints`

## Failure Handling Recommendation

Recommended client behavior:

1. If the response is `200`, show the normal scan result screen.
2. If the response is `422`, ask the user to retake the photo or crop closer to the label.
3. If the response is `404`, offer manual contribution or manual search if that flow exists in the app.

Recommended handling by `error_code`:

- `ocr_unreadable`: ask the user to retake the image with a clearer label or barcode
- `barcode_not_found`: offer manual contribution because the barcode was found but the product is not in the catalog
- `ocr_text_no_catalog_match`: offer manual search or contribution because OCR worked but no catalog match was found
