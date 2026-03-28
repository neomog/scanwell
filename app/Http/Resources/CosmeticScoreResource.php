<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CosmeticScoreResource extends JsonResource
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
            'irritant_score' => $this->irritant_score,
            'endocrine_score' => $this->endocrine_score,
            'allergen_score' => $this->allergen_score,
            'environmental_score' => $this->environmental_score,
            'safety_level' => $this->safety_level,
            'score_breakdown' => $this->score_breakdown,
            'explanation_text' => $this->explanation_text,
            'warnings' => $this->warnings,
            'benefits' => $this->benefits,
            'skin_types_suitable' => $this->skin_types_suitable,
            'calculated_at' => $this->calculated_at,
        ];
    }
}
