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
            'old_data' => $this->old_data,
            'new_data' => $this->new_data,
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
            'reviewed_at' => $this->reviewed_at,
            'created_at' => $this->created_at,
        ];
    }
}
