<?php

use App\Actions\MarkGenerationAsSubmitted;
use App\Enums\GenerationStatus;
use App\Models\Generation;

it('stores the Pixelforge id and moves a pending generation to queued', function () {
    $generation = Generation::factory()->create();

    app(MarkGenerationAsSubmitted::class)->handle($generation, 'gen_abc');

    expect($generation->fresh())
        ->pixelforge_id->toBe('gen_abc')
        ->status->toBe(GenerationStatus::Queued);
});

it('does not move a generation backwards when a webhook arrived first', function (GenerationStatus $status) {
    $generation = Generation::factory()->create(['status' => $status]);

    app(MarkGenerationAsSubmitted::class)->handle($generation, 'gen_abc');

    expect($generation->fresh())
        ->pixelforge_id->toBe('gen_abc')
        ->status->toBe($status);
})->with([GenerationStatus::Processing, GenerationStatus::Succeeded, GenerationStatus::Failed]);
