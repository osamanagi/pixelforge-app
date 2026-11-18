<?php

namespace App\Actions;

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Support\Str;

final readonly class CreateGeneration
{
    public function __construct(private PricingService $pricing) {}

    public function handle(User $user, string $prompt, GenerationSize $size, GenerationQuality $quality, array $referenceImages): Generation
    {
        $generation = $user->generations()->create([
            'prompt' => $prompt,
            'size' => $size,
            'quality' => $quality,
            'reference_images' => $referenceImages,
            'status' => GenerationStatus::Pending,
            'idempotency_key' => (string) Str::uuid(),
            'estimated_cost' => $this->pricing->estimatedPrice($prompt, $size, $quality, count($referenceImages)),
        ]);

        SubmitGeneration::dispatch($generation);

        return $generation;
    }
}
