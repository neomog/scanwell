<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductContributionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ],
            'product' => new ProductResource($this->whenLoaded('product')),
            'barcode' => $this->barcode,
            'product_name' => $this->product_name,
            'change_type' => $this->change_type,
            'field_name' => $this->field_name,
            'old_data' => $this->old_data,
            'new_data' => $this->new_data,
            'moderated_data' => $this->moderated_data,
            'reason' => $this->reason,
            'evidence' => $this->evidence,
            'status' => $this->status,
            'reviewer' => $this->whenLoaded('reviewer', function () {
                return [
                    'id' => $this->reviewer?->id,
                    'name' => $this->reviewer?->name,
                ];
            }),
            'review_notes' => $this->review_notes,
            'flag_reason' => $this->flag_reason,
            'flagged_at' => $this->flagged_at,
            'reputation_points_awarded' => $this->reputation_points_awarded,
            'meta' => $this->meta,
            'reviewed_at' => $this->reviewed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
