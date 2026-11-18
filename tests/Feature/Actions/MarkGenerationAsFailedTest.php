<?php

use App\Actions\MarkGenerationAsFailed;
use App\Enums\GenerationStatus;
use App\Models\Generation;

it('fails the generation at no cost', function () {
    $generation = Generation::factory()->queued()->create();

    app(MarkGenerationAsFailed::class)->handle($generation, ['code' => 'generation_failed', 'message' => 'Boom.']);

    expect($generation->fresh())
        ->status->toBe(GenerationStatus::Failed)
        ->error->toBe(['code' => 'generation_failed', 'message' => 'Boom.'])
        ->actual_cost->toBe(0)
        ->provider_cost->toBe(0)
        ->completed_at->not->toBeNull();
});

it('never overwrites a finished generation', function () {
    $generation = Generation::factory()->create(['status' => GenerationStatus::Succeeded, 'actual_cost' => 50_000_000]);

    app(MarkGenerationAsFailed::class)->handle($generation, ['code' => 'submission_failed', 'message' => 'Too late.']);

    expect($generation->fresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->actual_cost->toBe(50_000_000)
        ->error->toBeNull();
});
