<?php

use App\Actions\CreateGeneration;
use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('saves a pending generation with its estimated price and queues the submission', function () {
    Queue::fake();
    $user = User::factory()->create();

    $generation = app(CreateGeneration::class)->handle(
        $user,
        str_repeat('a', 400),
        GenerationSize::Landscape,
        GenerationQuality::High,
        ['https://example.com/a.png', 'https://example.com/b.png'],
    );

    expect($generation->fresh())
        ->user_id->toBe($user->id)
        ->status->toBe(GenerationStatus::Pending)
        ->estimated_cost->toBe(467_343_750)
        ->idempotency_key->toBeUuid();

    Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job) => $job->generation->is($generation));
});

it('gives every generation its own idempotency key', function () {
    Queue::fake();
    $user = User::factory()->create();
    $createGeneration = app(CreateGeneration::class);

    $first = $createGeneration->handle($user, 'a red fox', GenerationSize::Square, GenerationQuality::Low, []);
    $second = $createGeneration->handle($user, 'a red fox', GenerationSize::Square, GenerationQuality::Low, []);

    expect($first->idempotency_key)->not->toBe($second->idempotency_key);
});
