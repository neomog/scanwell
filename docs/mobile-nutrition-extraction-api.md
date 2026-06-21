# Mobile Nutrition Extraction API

## Overview

This endpoint is for the mobile product contribution flow.

When the user takes a photo of the nutrition panel, the app can upload that image and receive:

- `nutrition`: a normalized nutrition object keyed to the product contribution payload

## Endpoint

`POST /api/v1/contributions/nutrition/extract`

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

## Success Response

```json
{
  "success": true,
  "message": "Nutrition facts extracted successfully",
  "data": {
    "nutrition": {
      "serving_size": "1 sachet (30g)",
      "calories": 120,
      "fat": 8,
      "saturated_fat": 2,
      "trans_fat": 0,
      "cholesterol": 0,
      "sodium": 180,
      "carbohydrates": 16,
      "fiber": 3,
      "sugars": 6,
      "added_sugars": 4,
      "protein": 5
    },
    "extracted_text": "Nutrition Facts\nServing Size 1 sachet (30g)\nCalories 120\n...",
    "confidence": 0.93,
    "analysis_source": "google_cloud_vision",
    "ocr_mode": "DOCUMENT_TEXT_DETECTION"
  }
}
```

## Failure Response

```json
{
  "success": false,
  "message": "We could not extract readable nutrition facts from the image. Please retake the photo with the nutrition panel clearly visible."
}
```
