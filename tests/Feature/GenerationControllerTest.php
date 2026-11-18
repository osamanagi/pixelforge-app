<?php

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('redirects guests to the login page', function () {
    $this->get(route('generations.create'))->assertRedirect(route('login'));
});

it('shows the generate page with the available sizes and qualities', function () {
    $this->actingAs($this->user)
        ->get(route('generations.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('generations/Create')
            ->where('sizes', ['1024x1024', '1536x1024', '1024x1536'])
            ->where('qualities', ['low', 'medium', 'high']));
});

it('saves a pending generation with its estimated price and queues the submission', function () {
    Queue::fake();

    $this->actingAs($this->user)
        ->post(route('generations.store'), [
            'prompt' => str_repeat('a', 400),
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => ['https://example.com/a.png', '', 'https://example.com/b.png'],
        ])
        ->assertRedirect(route('generations.show', Generation::sole()));

    $generation = Generation::sole();

    expect($generation)
        ->user_id->toBe($this->user->id)
        ->status->toBe(GenerationStatus::Pending)
        ->size->toBe(GenerationSize::Landscape)
        ->quality->toBe(GenerationQuality::High)
        ->reference_images->toBe(['https://example.com/a.png', 'https://example.com/b.png'])
        ->estimated_cost->toBe(467_343_750)
        ->idempotency_key->toBeUuid();

    Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job) => $job->generation->is($generation));
});

it('rejects invalid generation requests', function (array $overrides, string $errorField) {
    Queue::fake();

    $this->actingAs($this->user)
        ->post(route('generations.store'), [
            'prompt' => 'a red fox',
            'size' => '1024x1024',
            'quality' => 'medium',
            ...$overrides,
        ])
        ->assertSessionHasErrors($errorField);

    expect(Generation::count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'missing prompt' => [['prompt' => ''], 'prompt'],
    'prompt too long' => [['prompt' => str_repeat('a', 4001)], 'prompt'],
    'unknown size' => [['size' => '512x512'], 'size'],
    'unknown quality' => [['quality' => 'ultra'], 'quality'],
    'too many reference images' => [['reference_images' => array_map(fn ($i) => "https://example.com/{$i}.png", range(1, 5))], 'reference_images'],
    'reference image is not a url' => [['reference_images' => ['not-a-url']], 'reference_images.0'],
    'reference image is not http' => [['reference_images' => ['ftp://example.com/a.png']], 'reference_images.0'],
]);

it('estimates the price before submitting', function () {
    $this->actingAs($this->user)
        ->postJson(route('generations.estimate'), [
            'prompt' => str_repeat('a', 400),
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_image_count' => 2,
        ])
        ->assertOk()
        ->assertExactJson(['estimated_price' => 467_343_750]);
});

it('estimates the price for an empty prompt', function () {
    $this->actingAs($this->user)
        ->postJson(route('generations.estimate'), [
            'prompt' => null,
            'size' => '1024x1024',
            'quality' => 'low',
            'reference_image_count' => 0,
        ])
        ->assertOk()
        ->assertJsonPath('estimated_price', 21_812_500);
});
