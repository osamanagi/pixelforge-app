<?php

namespace App\Services;

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use Illuminate\Container\Attributes\Config;

class PricingService
{
    /**
     * @param  array{markup_percent: int, rates: array<string, numeric-string>, characters_per_text_token: int, text_token_variance_percent: int, tokens_per_reference_image: int, output_tokens: array<string, array<string, int>>, max_reasoning_tokens: array<string, int>}  $pricing
     */
    public function __construct(
        #[Config('pixelforge')] private array $pricing,
    ) {}

    public function estimatedPrice(string $prompt, GenerationSize $size, GenerationQuality $quality, int $referenceImageCount): int
    {
        return $this->withMarkup($this->maximumProviderCost($prompt, $size, $quality, $referenceImageCount));
    }

    /**
     * @param  array<string, int|null>  $usage
     * @param  array<string, numeric-string>  $rates
     */
    public function providerCost(array $usage, array $rates): int
    {
        $cost = 0;

        foreach ($rates as $tokenType => $dollarsPerMillionTokens) {
            $cost += ($usage[$tokenType] ?? 0) * $this->nanodollarsPerToken($dollarsPerMillionTokens);
        }

        return $cost;
    }

    /**
     * @param  array<string, int|null>  $usage
     * @param  array<string, numeric-string>  $rates
     */
    public function price(array $usage, array $rates): int
    {
        return $this->withMarkup($this->providerCost($usage, $rates));
    }

    private function maximumProviderCost(string $prompt, GenerationSize $size, GenerationQuality $quality, int $referenceImageCount): int
    {
        return $this->providerCost([
            'input_text_tokens' => $this->maximumTextTokens($prompt),
            'input_image_tokens' => $referenceImageCount * $this->pricing['tokens_per_reference_image'],
            'output_tokens' => $this->pricing['output_tokens'][$quality->value][$size->value],
            'reasoning_tokens' => $this->pricing['max_reasoning_tokens'][$quality->value],
        ], $this->pricing['rates']);
    }

    private function maximumTextTokens(string $prompt): int
    {
        $estimatedTokens = $this->divideRoundingUp(mb_strlen($prompt), $this->pricing['characters_per_text_token']);

        return $this->divideRoundingUp($estimatedTokens * (100 + $this->pricing['text_token_variance_percent']), 100);
    }

    private function withMarkup(int $providerCost): int
    {
        return intdiv($providerCost * (100 + $this->pricing['markup_percent']), 100);
    }

    /**
     * @param  numeric-string  $dollarsPerMillionTokens
     */
    private function nanodollarsPerToken(string $dollarsPerMillionTokens): int
    {
        return (int) bcmul($dollarsPerMillionTokens, '1000');
    }

    private function divideRoundingUp(int $dividend, int $divisor): int
    {
        return intdiv($dividend + $divisor - 1, $divisor);
    }
}
