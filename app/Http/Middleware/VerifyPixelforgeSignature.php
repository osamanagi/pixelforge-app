<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class VerifyPixelforgeSignature
{
    private const int TOLERANCE_SECONDS = 300;

    public function __construct(
        #[Config('services.pixelforge.webhook_secret')] private string $secret,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->hasValidSignature($request), Response::HTTP_UNAUTHORIZED, 'Invalid Pixelforge signature.');

        return $next($request);
    }

    private function hasValidSignature(Request $request): bool
    {
        $signature = $this->parseSignatureHeader((string) $request->header('Pixelforge-Signature'));
        $timestamp = (string) $signature->get('t')?->first();

        if (! ctype_digit($timestamp) || abs(now()->getTimestamp() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', "{$timestamp}.{$request->getContent()}", $this->secret);

        return $signature->get('v1', collect())->contains(
            fn (string $candidate): bool => hash_equals($expectedSignature, $candidate)
        );
    }

    /**
     * @return Collection<string, Collection<int, string>>
     */
    private function parseSignatureHeader(string $header): Collection
    {
        return collect(explode(',', $header))->mapToGroups(function (string $element): array {
            [$key, $value] = array_pad(explode('=', trim($element), 2), 2, '');

            return [$key => $value];
        });
    }
}
