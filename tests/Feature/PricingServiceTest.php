<?php

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Services\PricingService;

beforeEach(function () {
    $this->pricing = app(PricingService::class);
    $this->rates = config('pixelforge.rates');
});

it('estimates the documented maximum provider cost plus markup', function () {
    $price = $this->pricing->estimatedPrice(str_repeat('a', 400), GenerationSize::Landscape, GenerationQuality::High, 2);

    expect($price)->toBe(467_343_750);
});

it('rounds text tokens up before and after the variance margin', function () {
    $price = $this->pricing->estimatedPrice('abcde', GenerationSize::Square, GenerationQuality::Low, 0);

    expect($price)->toBe(21_831_250);
});

it('matches the cost_usd of the documented usage report', function () {
    $usage = ['input_text_tokens' => 41, 'input_image_tokens' => 0, 'output_tokens' => 3300, 'reasoning_tokens' => 2950];

    expect($this->pricing->providerCost($usage, $this->rates))->toBe(169_080_000);
});

it('ignores usage fields that have no rate', function () {
    $usage = ['input_text_tokens' => 100, 'input_image_tokens' => 0, 'output_tokens' => 1100, 'reasoning_tokens' => 500, 'total_tokens' => 1700];

    expect($this->pricing->providerCost($usage, $this->rates))->toBe(50_750_000);
});

it('charges the provider cost plus a 25 percent markup', function () {
    $usage = ['input_text_tokens' => 41, 'input_image_tokens' => 0, 'output_tokens' => 3300, 'reasoning_tokens' => 2950];

    expect($this->pricing->price($usage, $this->rates))->toBe(211_350_000);
});

it('charges exactly the estimate when usage hits every maximum', function (GenerationSize $size, GenerationQuality $quality) {
    $prompt = str_repeat('a', 400);
    $usage = [
        'input_text_tokens' => 115,
        'input_image_tokens' => 765,
        'output_tokens' => config("pixelforge.output_tokens.{$quality->value}.{$size->value}"),
        'reasoning_tokens' => config("pixelforge.max_reasoning_tokens.{$quality->value}"),
    ];

    expect($this->pricing->price($usage, $this->rates))
        ->toBe($this->pricing->estimatedPrice($prompt, $size, $quality, 1));
})->with(GenerationSize::cases())->with(GenerationQuality::cases());
