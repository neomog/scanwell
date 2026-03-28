<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredientResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'risk_level' => $this->risk_level,
            'risk_color' => $this->risk_color ?? $this->getRiskColorAttribute(),
            'description' => $this->description,
            'scientific_reference' => $this->scientific_reference,
            'health_effects' => $this->health_effects,
            'aliases' => $this->aliases,
            'pivot' => [
                'percentage' => $this->whenPivotLoaded('product_ingredients', function () {
                    return $this->pivot->percentage;
                }),
                'is_additive' => $this->whenPivotLoaded('product_ingredients', function () {
                    return $this->pivot->is_additive;
                }),
                'origin' => $this->whenPivotLoaded('product_ingredients', function () {
                    return $this->pivot->origin;
                }),
            ],
        ];
    }

    protected function getRiskColorAttribute(): string
    {
        return match($this->risk_level) {
            'high' => 'red',
            'medium' => 'orange',
            'low' => 'green',
            default => 'gray',
        };
    }

//    public function toArray(Request $request): array
//    {
//        return [
//            'id' => $this->id,
//            'name' => $this->name,
//            'category' => $this->category,
//            'risk_level' => $this->risk_level,
//            'risk_color' => $this->risk_color,
//            'description' => $this->description,
//            'scientific_reference' => $this->scientific_reference,
//            'health_effects' => $this->health_effects,
//            'pivot' => [
//                'percentage' => $this->whenPivotLoaded('product_ingredients', function () {
//                    return $this->pivot->percentage;
//                }),
//                'is_additive' => $this->whenPivotLoaded('product_ingredients', function () {
//                    return $this->pivot->is_additive;
//                }),
//            ],
//        ];
//    }
}


