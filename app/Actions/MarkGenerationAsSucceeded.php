<?php

namespace App\Actions;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\PricingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class MarkGenerationAsSucceeded
{
    public function __construct(private PricingService $pricing) {}

    /**
     * @param  array<string, mixed>  $remoteGeneration
     */
    public function handle(Generation $generation, array $remoteGeneration): void
    {
        DB::transaction(function () use ($generation, $remoteGeneration) {
            $generation = Generation::query()->whereKey($generation->getKey())->lockForUpdate()->firstOrFail();

            if ($generation->status->isFinal()) {
                return;
            }

            $usage = $remoteGeneration['usage'];
            $rates = $remoteGeneration['rates'];

            $generation->pixelforge_id ??= $remoteGeneration['id'];

            $generation->forceFill([
                'status' => GenerationStatus::Succeeded,
                'usage' => $usage,
                'rates' => $rates,
                'provider_cost' => $this->pricing->providerCost($usage, $rates),
                'actual_cost' => $this->pricing->price($usage, $rates),
                'image_url' => $remoteGeneration['image_url'],
                'image_url_expires_at' => Carbon::parse($remoteGeneration['image_url_expires_at']),
                'completed_at' => Carbon::parse($remoteGeneration['completed_at']),
                'error' => null,
            ])->save();
        });
    }
}
