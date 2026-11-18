<?php

namespace App\Http\Resources;

use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Generation
 */
class GenerationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prompt' => $this->prompt,
            'size' => $this->size->value,
            'quality' => $this->quality->value,
            'reference_images' => $this->reference_images,
            'status' => $this->status->value,
            'is_finished' => $this->status->isFinal(),
            'estimated_cost' => $this->estimated_cost,
            'actual_cost' => $this->actual_cost,
            'image_url' => $this->image_url_expires_at?->isFuture() ? $this->image_url : null,
            'image_expired' => $this->image_url !== null && ! $this->image_url_expires_at?->isFuture(),
            'error_message' => $this->error['message'] ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
