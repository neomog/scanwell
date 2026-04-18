<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $images = $this->whenLoaded('images', function () {
            return $this->images->map(fn ($image) => [
                'id' => $image->id,
                'url' => $image->resolved_url,
                'source' => $image->source,
                'is_primary' => $image->is_primary,
                'sort_order' => $image->sort_order,
            ])->values();
        });

        $barcodes = $this->whenLoaded('barcodes', function () {
            return $this->barcodes->map(fn ($barcode) => [
                'id' => $barcode->id,
                'barcode' => $barcode->barcode,
                'label' => $barcode->label,
                'is_primary' => $barcode->is_primary,
            ])->values();
        });

        return [
            'id' => $this->id,
            'barcode' => $this->barcode,
            'barcodes' => $barcodes,
            'name' => $this->name,
            'brand' => $this->brand,
            'image_url' => $this->primary_image_url,
            'images' => $images,
            'category_id' => $this->category_id,
            'category_name' => $this->category_name,
            'product_family' => $this->resolved_product_family,
            'source' => $this->source,
            'ingredients_text' => $this->ingredients_text,
            'additives' => $this->additives ?? [],
            'allergens' => $this->allergens ?? [],
            'region_availability' => $this->region_availability ?? [],
            'ingredients' => IngredientResource::collection($this->whenLoaded('ingredients')),
            'nutrition' => new FoodNutritionResource($this->whenLoaded('nutrition')),
            'scores' => [
                'food' => new FoodScoreResource($this->whenLoaded('foodScore')),
                'cosmetic' => new CosmeticScoreResource($this->whenLoaded('cosmeticScore')),
            ],
            'alternatives' => ProductResource::collection($this->whenLoaded('alternatives')),
            'score' => $this->score,
            'score_grade' => $this->score_grade,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
