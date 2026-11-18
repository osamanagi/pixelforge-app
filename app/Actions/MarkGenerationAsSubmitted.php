<?php

namespace App\Actions;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Support\Facades\DB;

final readonly class MarkGenerationAsSubmitted
{
    public function handle(Generation $generation, string $pixelforgeId): void
    {
        DB::transaction(function () use ($generation, $pixelforgeId) {
            $generation = Generation::query()->lockForUpdate()->findOrFail($generation->getKey());

            $generation->pixelforge_id ??= $pixelforgeId;

            if ($generation->status === GenerationStatus::Pending) {
                $generation->status = GenerationStatus::Queued;
            }

            $generation->save();
        });
    }
}
