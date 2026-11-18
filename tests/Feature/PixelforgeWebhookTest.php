<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\WebhookEvent;

beforeEach(function () {
    $this->generation = Generation::factory()->create([
        'status' => GenerationStatus::Queued,
        'estimated_cost' => 86_343_750,
    ]);
    $this->generation->forceFill(['pixelforge_id' => 'gen_01M7QQFQ6CMCHYVB3P8NHXW4JB'])->save();
});

it('records the exact cost of a succeeded generation', function () {
    postPixelforgeWebhook(pixelforgeEvent('generation.succeeded'))->assertNoContent();

    expect($this->generation->fresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->provider_cost->toBe(51_902_500)
        ->actual_cost->toBe(64_878_125)
        ->usage->toMatchArray(['reasoning_tokens' => 627])
        ->image_url->toStartWith('https://pixelforge.promptedit.dev/images/gen_01M7QQFQ6CMCHYVB3P8NHXW4JB.png')
        ->image_url_expires_at->toDateTimeString()->toBe('2026-11-19 16:23:05')
        ->completed_at->toDateTimeString()->toBe('2026-11-18 16:23:05');
});

it('records an event delivered twice only once', function () {
    postPixelforgeWebhook(pixelforgeEvent('generation.succeeded'))->assertNoContent();
    postPixelforgeWebhook(pixelforgeEvent('generation.succeeded', [
        'usage' => ['input_text_tokens' => 9999, 'input_image_tokens' => 0, 'output_tokens' => 9999, 'reasoning_tokens' => 9999],
    ]))->assertNoContent();

    expect(WebhookEvent::count())->toBe(1)
        ->and($this->generation->fresh()->actual_cost)->toBe(64_878_125);
});

it('ignores a processing event that arrives after the generation succeeded', function () {
    postPixelforgeWebhook(pixelforgeEvent('generation.succeeded', eventId: 'evt_succeeded'));
    postPixelforgeWebhook(pixelforgeEvent('generation.processing', ['status' => 'processing', 'usage' => null], 'evt_processing'));

    expect($this->generation->fresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->actual_cost->toBe(64_878_125);
});

it('marks a queued generation as processing', function () {
    postPixelforgeWebhook(pixelforgeEvent('generation.processing', ['status' => 'processing', 'usage' => null]));

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Processing);
});

it('records a failed generation at no cost', function () {
    postPixelforgeWebhook(pixelforgeEvent('generation.failed', [
        'status' => 'failed',
        'usage' => null,
        'image_url' => null,
        'error' => ['code' => 'generation_failed', 'message' => 'The generation failed.'],
    ]));

    expect($this->generation->fresh())
        ->status->toBe(GenerationStatus::Failed)
        ->actual_cost->toBe(0)
        ->provider_cost->toBe(0)
        ->error->toBe(['code' => 'generation_failed', 'message' => 'The generation failed.'])
        ->completed_at->toDateTimeString()->toBe('2026-11-18 16:23:05');
});

it('never changes a failed generation into a succeeded one', function () {
    postPixelforgeWebhook(pixelforgeEvent('generation.failed', ['status' => 'failed', 'error' => ['code' => 'generation_failed']], 'evt_failed'));
    postPixelforgeWebhook(pixelforgeEvent('generation.succeeded', eventId: 'evt_succeeded'));

    expect($this->generation->fresh())
        ->status->toBe(GenerationStatus::Failed)
        ->actual_cost->toBe(0);
});

it('finds the generation by its metadata when the webhook beats the submission response', function () {
    $pending = Generation::factory()->create();

    postPixelforgeWebhook(pixelforgeEvent('generation.processing', [
        'id' => 'gen_new',
        'status' => 'processing',
        'metadata' => ['generation_id' => (string) $pending->id],
    ]));

    expect($pending->fresh())
        ->pixelforge_id->toBe('gen_new')
        ->status->toBe(GenerationStatus::Processing);
});

it('does not match metadata to a generation that already belongs to another Pixelforge id', function () {
    postPixelforgeWebhook(pixelforgeEvent('generation.succeeded', [
        'id' => 'gen_someone_else',
        'metadata' => ['generation_id' => (string) $this->generation->id],
    ]))->assertNoContent();

    expect($this->generation->fresh()->status)->toBe(GenerationStatus::Queued);
});

it('acknowledges events for unknown generations and pings', function () {
    postPixelforgeWebhook(pixelforgeEvent('generation.succeeded', ['id' => 'gen_unknown', 'metadata' => []], 'evt_unknown'))->assertNoContent();
    postPixelforgeWebhook(['id' => 'evt_ping', 'object' => 'event', 'type' => 'ping', 'data' => []])->assertNoContent();

    expect(WebhookEvent::count())->toBe(2)
        ->and($this->generation->fresh()->status)->toBe(GenerationStatus::Queued);
});

it('rejects a malformed event', function () {
    postPixelforgeWebhook(['type' => 'generation.succeeded'])->assertUnprocessable();

    expect(WebhookEvent::count())->toBe(0);
});
