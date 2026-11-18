<?php

namespace App\Console\Commands;

use App\Actions\SyncGenerationWithPixelforge;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\PixelforgeClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

#[Signature('generations:sync-pending')]
#[Description('Fetch generations from Pixelforge that have not finished after two minutes, in case a webhook was missed')]
class SyncPendingGenerations extends Command
{
    private const int STALE_AFTER_MINUTES = 2;

    public function handle(PixelforgeClient $pixelforge, SyncGenerationWithPixelforge $syncGeneration): int
    {
        $synced = 0;

        Generation::query()
            ->whereNotNull('pixelforge_id')
            ->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
            ->where('updated_at', '<=', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->lazyById()
            ->each(function (Generation $generation) use ($pixelforge, $syncGeneration, &$synced) {
                try {
                    $syncGeneration->handle($pixelforge->getGeneration((string) $generation->pixelforge_id));
                    $synced++;
                } catch (ConnectionException|RequestException $exception) {
                    report($exception);
                    $this->components->warn("Could not fetch {$generation->pixelforge_id}: {$exception->getMessage()}");
                }
            });

        $this->components->info("Synced {$synced} generation(s) with Pixelforge.");

        return self::SUCCESS;
    }
}
