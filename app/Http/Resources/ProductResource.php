<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'brand' => $this->brand,
            'image_url' => $this->image_url,
            'category_id' => $this->category_id,
            'source' => $this->source,
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
