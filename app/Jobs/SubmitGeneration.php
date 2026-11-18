<?php

namespace App\Jobs;

use App\Actions\MarkGenerationAsFailed;
use App\Actions\MarkGenerationAsSubmitted;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\PixelforgeClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;

class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    private const int DEFAULT_RELEASE_SECONDS = 10;

    public int $tries = 10;

    public function __construct(public Generation $generation) {}

    public function handle(PixelforgeClient $pixelforge, MarkGenerationAsSubmitted $markAsSubmitted, MarkGenerationAsFailed $markAsFailed): void
    {
        if ($this->generation->status !== GenerationStatus::Pending) {
            return;
        }

        try {
            $remoteGeneration = $pixelforge->createGeneration($this->generation);
        } catch (ConnectionException) {
            $this->release(self::DEFAULT_RELEASE_SECONDS);

            return;
        } catch (RequestException $exception) {
            $this->handleRejectedRequest($exception->response, $markAsFailed);

            return;
        }

        $markAsSubmitted->handle($this->generation, $remoteGeneration['id']);
    }

    public function failed(?Throwable $exception): void
    {
        app(MarkGenerationAsFailed::class)->handle($this->generation, [
            'code' => 'submission_failed',
            'message' => __('Pixelforge is unavailable right now. Please try again later.'),
        ]);
    }

    private function handleRejectedRequest(Response $response, MarkGenerationAsFailed $markAsFailed): void
    {
        if ($this->isWorthRetryingLater($response)) {
            $this->release((int) $response->header('Retry-After') ?: self::DEFAULT_RELEASE_SECONDS);

            return;
        }

        $markAsFailed->handle($this->generation, $response->json('error') ?? [
            'code' => 'request_failed',
            'message' => __('Pixelforge rejected the generation.'),
        ]);
    }

    private function isWorthRetryingLater(Response $response): bool
    {
        return $response->serverError()
            || ($response->tooManyRequests() && $response->json('error.code') !== 'daily_limit_exceeded');
    }
}
