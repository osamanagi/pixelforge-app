<?php

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Models\Generation;
use App\Services\PixelforgeClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    config([
        'services.pixelforge.base_url' => 'https://pixelforge.test/v1',
        'services.pixelforge.api_key' => 'test-key',
    ]);

    Http::preventStrayRequests();
    Sleep::fake();

    $this->generation = (new Generation)->forceFill([
        'id' => 7,
        'idempotency_key' => 'f3b9c2a4-1d2e-4f5a-8b6c-7d8e9f0a1b2c',
        'prompt' => 'a red fox',
        'size' => GenerationSize::Landscape,
        'quality' => GenerationQuality::High,
        'reference_images' => ['https://example.com/fox.png'],
    ]);
});

function pixelforgeError(int $status, string $code, ?int $retryAfter = null)
{
    return Http::response(['error' => ['code' => $code]], $status, $retryAfter ? ['Retry-After' => $retryAfter] : []);
}

it('creates a generation with the idempotency key and request fields', function () {
    Http::fake(['pixelforge.test/v1/generations' => Http::response(['id' => 'gen_123', 'status' => 'queued'], 202)]);

    $response = app(PixelforgeClient::class)->createGeneration($this->generation);

    expect($response)->toBe(['id' => 'gen_123', 'status' => 'queued']);

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-key')
        && $request->hasHeader('Idempotency-Key', 'f3b9c2a4-1d2e-4f5a-8b6c-7d8e9f0a1b2c')
        && $request->data() === [
            'prompt' => 'a red fox',
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => ['https://example.com/fox.png'],
            'metadata' => ['generation_id' => '7'],
        ]);
});

it('retries an overloaded response with the same idempotency key', function () {
    Http::fake(['pixelforge.test/v1/generations' => Http::sequence()
        ->pushResponse(pixelforgeError(503, 'overloaded'))
        ->push(['id' => 'gen_123'], 202)]);

    app(PixelforgeClient::class)->createGeneration($this->generation);

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Idempotency-Key', 'f3b9c2a4-1d2e-4f5a-8b6c-7d8e9f0a1b2c'));
    Sleep::assertSequence([Sleep::for(2)->seconds()]);
});

it('waits for the Retry-After header on a concurrency limit', function () {
    Http::fake(['pixelforge.test/v1/generations' => Http::sequence()
        ->pushResponse(pixelforgeError(429, 'concurrency_limit_exceeded', 5))
        ->push(['id' => 'gen_123'], 202)]);

    app(PixelforgeClient::class)->createGeneration($this->generation);

    Http::assertSentCount(2);
    Sleep::assertSequence([Sleep::for(5)->seconds()]);
});

it('gives up after three attempts', function () {
    Http::fake(['pixelforge.test/v1/generations' => pixelforgeError(503, 'overloaded')]);

    expect(fn () => app(PixelforgeClient::class)->createGeneration($this->generation))
        ->toThrow(RequestException::class);

    Http::assertSentCount(3);
});

it('does not retry errors that will not resolve by waiting briefly', function (int $status, string $code, ?int $retryAfter) {
    Http::fake(['pixelforge.test/v1/generations' => pixelforgeError($status, $code, $retryAfter)]);

    expect(fn () => app(PixelforgeClient::class)->createGeneration($this->generation))
        ->toThrow(RequestException::class);

    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
})->with([
    'content policy' => [400, 'content_policy_violation', null],
    'validation' => [422, 'validation_failed', null],
    'daily limit' => [429, 'daily_limit_exceeded', 3600],
    'long rate limit wait' => [429, 'rate_limit_exceeded', 30],
]);

it('fetches a generation by its Pixelforge id', function () {
    Http::fake(['pixelforge.test/v1/generations/gen_123' => Http::response(['id' => 'gen_123', 'status' => 'succeeded'])]);

    expect(app(PixelforgeClient::class)->getGeneration('gen_123'))
        ->toBe(['id' => 'gen_123', 'status' => 'succeeded']);
});
