<?php

use App\Models\WebhookEvent;

beforeEach(function () {
    $this->freezeTime();
    $this->ping = ['id' => 'evt_ping', 'object' => 'event', 'type' => 'ping', 'data' => []];
});

it('accepts a correctly signed event', function () {
    postPixelforgeWebhook($this->ping)->assertNoContent();

    expect(WebhookEvent::count())->toBe(1);
});

it('rejects an event without a signature', function () {
    $this->postJson(route('webhooks.pixelforge'), $this->ping)->assertUnauthorized();
});

it('rejects an event signed with another secret', function () {
    postPixelforgeWebhook($this->ping, secret: 'whsec_wrong')->assertUnauthorized();
});

it('rejects a replayed event outside the five minute window', function (int $secondsAgo) {
    postPixelforgeWebhook($this->ping, now()->timestamp - $secondsAgo)->assertUnauthorized();
})->with([301, -301]);

it('accepts an event signed just inside the five minute window', function () {
    postPixelforgeWebhook($this->ping, now()->timestamp - 300)->assertNoContent();
});

it('rejects a body that was changed after signing', function () {
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.json_encode($this->ping), config('services.pixelforge.webhook_secret'));

    $this->call('POST', route('webhooks.pixelforge'), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], content: json_encode([...$this->ping, 'id' => 'evt_forged']))->assertUnauthorized();

    expect(WebhookEvent::count())->toBe(0);
});
