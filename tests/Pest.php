<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function pixelforgeEvent(string $type, array $generation = [], string $eventId = 'evt_01M7QQG41BVCFV1VWG2V7F8F02'): array
{
    return [
        'id' => $eventId,
        'object' => 'event',
        'type' => $type,
        'created_at' => '2026-11-18T16:23:05Z',
        'data' => ['object' => [
            'id' => 'gen_01M7QQFQ6CMCHYVB3P8NHXW4JB',
            'object' => 'generation',
            'status' => 'succeeded',
            'prompt' => 'generate an image for DHH driving a formula F1 car',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'metadata' => ['generation_id' => '1'],
            'image_url' => 'https://pixelforge.promptedit.dev/images/gen_01M7QQFQ6CMCHYVB3P8NHXW4JB.png?expires=1795105385&signature=abc',
            'image_url_expires_at' => '2026-11-19T16:23:05Z',
            'usage' => ['input_text_tokens' => 13, 'input_image_tokens' => 0, 'output_tokens' => 1100, 'reasoning_tokens' => 627, 'total_tokens' => 1740],
            'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
            'error' => null,
            'created_at' => '2026-11-18T16:22:52Z',
            'started_at' => '2026-11-18T16:22:55Z',
            'completed_at' => '2026-11-18T16:23:05Z',
            ...$generation,
        ]],
    ];
}

function postPixelforgeWebhook(array $event, ?int $timestamp = null, ?string $secret = null): TestResponse
{
    $body = json_encode($event);
    $timestamp ??= now()->timestamp;
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret ?? config('services.pixelforge.webhook_secret'));

    return test()->call('POST', route('webhooks.pixelforge'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], content: $body);
}
