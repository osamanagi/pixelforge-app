<?php

namespace App\Actions;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

final readonly class SyncGenerationWithPixelforge
{
    public function __construct(
        private MarkGenerationAsSubmitted $markAsSubmitted,
        private MarkGenerationAsProcessing $markAsProcessing,
        private MarkGenerationAsSucceeded $markAsSucceeded,
        private MarkGenerationAsFailed $markAsFailed,
    ) {}

    /**
     * @param  array<string, mixed>  $remoteGeneration
     */
    public function handle(array $remoteGeneration): void
    {
        $generation = $this->findGeneration($remoteGeneration);

        if ($generation === null) {
            Log::warning('Pixelforge generation does not match any local generation.', ['pixelforge_id' => $remoteGeneration['id']]);

            return;
        }

        match (GenerationStatus::tryFrom($remoteGeneration['status'])) {
            GenerationStatus::Queued => $this->markAsSubmitted->handle($generation, $remoteGeneration['id']),
            GenerationStatus::Processing => $this->markAsProcessing->handle($generation, $remoteGeneration['id']),
            GenerationStatus::Succeeded => $this->markAsSucceeded->handle($generation, $remoteGeneration),
            GenerationStatus::Failed => $this->markAsFailed->handle(
                $generation,
                $remoteGeneration['error'] ?? ['code' => 'generation_failed', 'message' => __('The generation failed.')],
                isset($remoteGeneration['completed_at']) ? Carbon::parse($remoteGeneration['completed_at']) : null,
            ),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $remoteGeneration
     */
    private function findGeneration(array $remoteGeneration): ?Generation
    {
        $localId = $remoteGeneration['metadata']['generation_id'] ?? null;

        return Generation::query()
            ->where('pixelforge_id', $remoteGeneration['id'])
            ->when($localId !== null, fn (Builder $query) => $query->orWhere(
                fn (Builder $query) => $query->whereKey((int) $localId)->whereNull('pixelforge_id')
            ))
            ->first();
    }
}
