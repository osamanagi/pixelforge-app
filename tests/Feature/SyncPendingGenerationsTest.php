<?php

use App\Console\Commands\SyncPendingGenerations;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();
});

function stuckGeneration(string $pixelforgeId, GenerationStatus $status = GenerationStatus::Processing, int $minutesAgo = 5): Generation
{
    $generation = Generation::factory()->create(['status' => $status]);
    $generation->forceFill(['pixelforge_id' => $pixelforgeId, 'updated_at' => now()->subMinutes($minutesAgo)])->saveQuietly();

    return $generation;
}

it('records a generation whose webhook never arrived', function () {
    $generation = stuckGeneration('gen_01M7QQFQ6CMCHYVB3P8NHXW4JB');
    Http::fake(['pixelforge.test/v1/generations/gen_01M7QQFQ6CMCHYVB3P8NHXW4JB' => Http::response(pixelforgeEvent('generation.succeeded')['data']['object'])]);

    $this->artisan(SyncPendingGenerations::class)->assertSuccessful();

    expect($generation->fresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->provider_cost->toBe(51_902_500)
        ->actual_cost->toBe(64_878_125);
});

it('records a failure it finds while polling at no cost', function () {
    $generation = stuckGeneration('gen_failed', GenerationStatus::Queued);
    Http::fake(['pixelforge.test/v1/generations/gen_failed' => Http::response(pixelforgeEvent('generation.failed', [
        'id' => 'gen_failed',
        'status' => 'failed',
        'usage' => null,
        'error' => ['code' => 'generation_failed', 'message' => 'The generation failed.'],
    ])['data']['object'])]);

    $this->artisan(SyncPendingGenerations::class)->assertSuccessful();

    expect($generation->fresh())
        ->status->toBe(GenerationStatus::Failed)
        ->actual_cost->toBe(0);
});

it('leaves recent, finished and unsubmitted generations alone', function () {
    stuckGeneration('gen_recent', minutesAgo: 1);
    stuckGeneration('gen_done', GenerationStatus::Succeeded);
    Generation::factory()->create(['updated_at' => now()->subHour()]);
    Http::fake();

    $this->artisan(SyncPendingGenerations::class)->assertSuccessful();

    Http::assertNothingSent();
});

it('keeps going when one generation cannot be fetched', function () {
    $broken = stuckGeneration('gen_broken');
    $healthy = stuckGeneration('gen_01M7QQFQ6CMCHYVB3P8NHXW4JB');
    Http::fake([
        'pixelforge.test/v1/generations/gen_broken' => Http::response(['error' => ['code' => 'not_found']], 404),
        'pixelforge.test/v1/generations/gen_01M7QQFQ6CMCHYVB3P8NHXW4JB' => Http::response(pixelforgeEvent('generation.succeeded')['data']['object']),
    ]);

    $this->artisan(SyncPendingGenerations::class)->assertSuccessful();

    expect($broken->fresh()->status)->toBe(GenerationStatus::Processing)
        ->and($healthy->fresh()->status)->toBe(GenerationStatus::Succeeded);
});

it('runs every minute without overlapping', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains((string) $event->command, 'generations:sync-pending'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
