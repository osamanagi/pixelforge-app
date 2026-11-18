<?php

namespace App\Models;

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Enums\GenerationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Generation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'size' => GenerationSize::class,
            'quality' => GenerationQuality::class,
            'status' => GenerationStatus::class,
            'reference_images' => 'array',
            'rates' => 'array',
            'usage' => 'array',
            'error' => 'array',
            'estimated_cost' => 'integer',
            'actual_cost' => 'integer',
            'provider_cost' => 'integer',
            'image_url_expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
