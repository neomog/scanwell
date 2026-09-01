**# Mobile Ingredient Extraction API

## Overview

This endpoint is for the mobile product contribution flow.

When the user takes a photo of the ingredient panel, the app can upload that image and receive:

- `ingredients_text`: a cleaned ingredient string suitable for filling the ingredient text field
- `ingredients`: a split ingredient array if the app wants to prebuild chips or rows

## Endpoint

`POST /api/v1/contributions/ingredients/extract`

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

### Field Notes

- `image`: a photo of the ingredients panel or ingredient label

## Sample Request

```bash
curl --request POST \
  --url https://scanwell.ohiare.com/api/v1/contributions/ingredients/extract \
  --header "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  --header "Accept: application/json" \
  --form "image=@/path/to/ingredients.jpg"
```

## Success Response

```json
{
  "success": true,
  "message": "Ingredients extracted successfully",
  "data": {
    "ingredients_text": "Water, Sugar, Citric Acid, Natural Flavors",
    "ingredients": [
      "Water",
      "Sugar",
      "Citric Acid",
      "Natural Flavors"
    ],
    "extracted_text": "Ingredients: Water, Sugar, Citric Acid, Natural Flavors",
    "confidence": 0.91,
    "analysis_source": "google_cloud_vision",
    "ocr_mode": "DOCUMENT_TEXT_DETECTION"
  }
}
```

## Failure Response

```json
{
  "success": false,
  "message": "We could not extract a readable ingredient list from the image. Please retake the photo with the ingredients panel clearly visible."
}
```

## Mobile Integration Notes

- Use `data.ingredients_text` to autofill the main ingredient text field.
- Use `data.ingredients` if the contribution UI supports per-ingredient rows or chips.
- If the endpoint returns `422`, prompt the user to retake the photo closer to the ingredients panel with better lighting.**
