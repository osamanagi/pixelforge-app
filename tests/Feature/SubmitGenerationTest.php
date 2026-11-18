<?php

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();

    $this->generation = Generation::factory()->create();
});

function pixelforgeRejection(int $status, string $code, ?int $retryAfter = null)
{
    return Http::response(
        ['error' => ['type' => 'error', 'code' => $code, 'message' => "Pixelforge said {$code}."]],
        $status,
        $retryAfter ? ['Retry-After' => $retryAfter] : [],
    );
}

it('stores the Pixelforge id and marks the generation as queued', function () {
    Http::fake(['pixelforge.test/v1/generations' => Http::response(['id' => 'gen_abc', 'status' => 'queued'], 202)]);

    SubmitGeneration::dispatch($this->generation);

    expect($this->generation->fresh())
        ->pixelforge_id->toBe('gen_abc')
        ->status->toBe(GenerationStatus::Queued);
});

it('does not submit a generation twice', function () {
    $this->generation->forceFill(['pixelforge_id' => 'gen_abc', 'status' => GenerationStatus::Queued])->save();

    SubmitGeneration::dispatch($this->generation);

    Http::assertNothingSent();
});

it('fails the generation for free when Pixelforge rejects it', function (int $status, string $code) {
    Http::fake(['pixelforge.test/v1/generations' => pixelforgeRejection($status, $code)]);

    SubmitGeneration::dispatch($this->generation);

    expect($this->generation->fresh())
        ->status->toBe(GenerationStatus::Failed)
        ->error->toMatchArray(['code' => $code, 'message' => "Pixelforge said {$code}."])
        ->actual_cost->toBe(0)
        ->provider_cost->toBe(0)
        ->completed_at->not->toBeNull();
})->with([
    'content policy' => [400, 'content_policy_violation'],
    'daily limit' => [429, 'daily_limit_exceeded'],
]);

it('releases the job to try again later when Pixelforge is busy', function () {
    Http::fake(['pixelforge.test/v1/generations' => pixelforgeRejection(429, 'rate_limit_exceeded', 30)]);

    $job = (new SubmitGeneration($this->generation))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    $job->assertReleased(30);
    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Pending);
});

it('fails the generation for free when every attempt fails', function () {
    (new SubmitGeneration($this->generation))->failed(new RuntimeException);

    expect($this->generation->fresh())
        ->status->toBe(GenerationStatus::Failed)
        ->error->toMatchArray(['code' => 'submission_failed'])
        ->actual_cost->toBe(0);
});
