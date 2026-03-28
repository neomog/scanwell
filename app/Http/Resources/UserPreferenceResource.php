<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'diet_type' => $this->diet_type,
            'allergies' => $this->allergies ?? [],
            'skin_type' => $this->skin_type,
            'health_goals' => $this->health_goals ?? [],
            'avoid_ingredients' => $this->avoid_ingredients ?? [],
            'min_score_threshold' => $this->min_score_threshold,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
