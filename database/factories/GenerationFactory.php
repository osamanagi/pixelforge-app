<?php

namespace Database\Factories;

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Enums\GenerationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GenerationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'idempotency_key' => (string) Str::uuid(),
            'prompt' => fake()->sentence(),
            'size' => GenerationSize::Square,
            'quality' => GenerationQuality::Medium,
            'reference_images' => [],
            'status' => GenerationStatus::Pending,
            'estimated_cost' => 100_000_000,
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (): array => [
            'pixelforge_id' => 'gen_'.Str::random(12),
            'status' => GenerationStatus::Queued,
        ]);
    }
}
