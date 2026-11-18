<?php

namespace App\Actions;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final readonly class MarkGenerationAsFailed
{
    /**
     * @param  array<string, mixed>  $error
     */
    public function handle(Generation $generation, array $error, ?CarbonInterface $completedAt = null): void
    {
        DB::transaction(function () use ($generation, $error, $completedAt) {
            $generation = Generation::query()->whereKey($generation->getKey())->lockForUpdate()->firstOrFail();

            if ($generation->status->isFinal()) {
                return;
            }

            $generation->forceFill([
                'status' => GenerationStatus::Failed,
                'error' => $error,
                'actual_cost' => 0,
                'provider_cost' => 0,
                'completed_at' => $completedAt ?? now(),
            ])->save();
        });
    }
}
