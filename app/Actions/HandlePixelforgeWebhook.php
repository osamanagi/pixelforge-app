<?php

namespace App\Actions;

use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class HandlePixelforgeWebhook
{
    public function __construct(private SyncGenerationWithPixelforge $syncGeneration) {}

    public function handle(array $event): void
    {
        DB::transaction(function () use ($event) {
            if (! $this->recordForTheFirstTime($event)) {
                return;
            }

            if (Str::startsWith($event['type'], 'generation.')) {
                $this->syncGeneration->handle($event['data']['object']);
            }
        });
    }

    private function recordForTheFirstTime(array $event): bool
    {
        return WebhookEvent::query()->insertOrIgnore([
            'event_id' => $event['id'],
            'type' => $event['type'],
            'payload' => json_encode($event),
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }
}
