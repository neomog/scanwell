<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FoodScoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'overall_score' => $this->overall_score,
            'nutrition_score' => $this->nutrition_score,
            'ingredient_score' => $this->ingredient_score,
            'additive_score' => $this->additive_score,
            'processing_score' => $this->processing_score,
            'grade' => $this->grade,
            'color' => $this->score_color,
            'nova_group' => $this->nova_group,
            'nutriscore_grade' => $this->nutriscore_grade,
            'score_breakdown' => $this->score_breakdown,
            'explanation_text' => $this->explanation_text,
            'warnings' => $this->warnings,
            'benefits' => $this->benefits,
            'calculated_at' => $this->calculated_at,
        ];
    }
}
