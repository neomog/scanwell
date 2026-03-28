<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FoodNutritionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'calories' => $this->calories,
            'fat' => $this->fat,
            'saturated_fat' => $this->saturated_fat,
            'trans_fat' => $this->trans_fat,
            'cholesterol' => $this->cholesterol,
            'sodium' => $this->sodium,
            'carbohydrates' => $this->carbohydrates,
            'fiber' => $this->fiber,
            'sugars' => $this->sugars,
            'added_sugars' => $this->added_sugars,
            'protein' => $this->protein,
            'vitamin_d' => $this->vitamin_d,
            'calcium' => $this->calcium,
            'iron' => $this->iron,
            'potassium' => $this->potassium,
            'vitamins' => $this->vitamins,
            'minerals' => $this->minerals,
            'serving_size' => $this->serving_size,
            'servings_per_container' => $this->servings_per_container,
            'health_score' => $this->when($this->resource, function () {
                return $this->calculateHealthScore();
            }),
        ];
    }
}
