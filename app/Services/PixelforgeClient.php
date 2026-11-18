<?php

namespace App\Services;

use App\Models\Generation;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class PixelforgeClient
{
    private const int MAX_ATTEMPTS = 3;

    private const int MAX_RETRY_WAIT_SECONDS = 10;

    public function __construct(
        #[Config('services.pixelforge.base_url')] private string $baseUrl,
        #[Config('services.pixelforge.api_key')] private string $apiKey,
    ) {}

    public function createGeneration(Generation $generation): array
    {
        return $this->request()
            ->withHeader('Idempotency-Key', $generation->idempotency_key)
            ->post('generations', [
                'prompt' => $generation->prompt,
                'size' => $generation->size->value,
                'quality' => $generation->quality->value,
                'reference_images' => $generation->reference_images,
                'metadata' => ['generation_id' => (string) $generation->id],
            ])
            ->json();
    }

    public function getGeneration(string $pixelforgeId): array
    {
        return $this->request()
            ->get("generations/{$pixelforgeId}")
            ->json();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->retry(self::MAX_ATTEMPTS, $this->retryDelayInMilliseconds(...), $this->isRetryable(...));
    }

    private function isRetryable(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException) {
            return false;
        }

        $response = $exception->response;

        if ($response->serverError()) {
            return true;
        }

        return $response->tooManyRequests()
            && $response->json('error.code') !== 'daily_limit_exceeded'
            && ($this->retryAfterSeconds($response) ?? 0) <= self::MAX_RETRY_WAIT_SECONDS;
    }

    private function retryDelayInMilliseconds(int $attempt, Throwable $exception): int
    {
        $retryAfterSeconds = $exception instanceof RequestException
            ? $this->retryAfterSeconds($exception->response)
            : null;

        return ($retryAfterSeconds ?? 2 * $attempt) * 1000;
    }

    private function retryAfterSeconds(Response $response): ?int
    {
        $retryAfter = $response->header('Retry-After');

        return $retryAfter === '' ? null : (int) $retryAfter;
    }
}
