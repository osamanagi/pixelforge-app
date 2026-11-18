<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->generation = Generation::factory()->for($this->user)->create(['prompt' => 'A watercolor fox reading a newspaper']);
});

it('redirects guests to the login page', function () {
    $this->get(route('generations.show', $this->generation))->assertRedirect(route('login'));
});

it('shows the prompt and the result of a generation', function () {
    $this->actingAs($this->user)
        ->get(route('generations.show', $this->generation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('generations/Show')
            ->where('generation.id', $this->generation->id)
            ->where('generation.prompt', 'A watercolor fox reading a newspaper')
            ->where('generation.status', 'pending')
            ->where('generation.is_finished', false));
});

it('does not show another user\'s generation', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('generations.show', $this->generation))
        ->assertForbidden();
});

it('shows the estimated and actual cost of a finished generation', function () {
    $this->generation->forceFill([
        'status' => GenerationStatus::Succeeded,
        'estimated_cost' => 86_343_750,
        'actual_cost' => 64_878_125,
        'image_url' => 'https://pixelforge.promptedit.dev/images/gen_1.png',
        'image_url_expires_at' => now()->addDay(),
    ])->save();

    $this->actingAs($this->user)
        ->get(route('generations.show', $this->generation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('generation.status', 'succeeded')
            ->where('generation.is_finished', true)
            ->where('generation.estimated_cost', 86_343_750)
            ->where('generation.actual_cost', 64_878_125)
            ->where('generation.image_url', 'https://pixelforge.promptedit.dev/images/gen_1.png')
            ->where('generation.image_expired', false));
});

it('hides image links that have expired', function () {
    $this->generation->forceFill([
        'status' => GenerationStatus::Succeeded,
        'image_url' => 'https://pixelforge.promptedit.dev/images/gen_1.png',
        'image_url_expires_at' => now()->subMinute(),
    ])->save();

    $this->actingAs($this->user)
        ->get(route('generations.show', $this->generation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('generation.image_url', null)
            ->where('generation.image_expired', true));
});

it('explains why a generation failed', function () {
    $this->generation->forceFill([
        'status' => GenerationStatus::Failed,
        'actual_cost' => 0,
        'error' => ['code' => 'content_policy_violation', 'message' => 'The prompt was rejected.'],
    ])->save();

    $this->actingAs($this->user)
        ->get(route('generations.show', $this->generation))
        ->assertInertia(fn (Assert $page) => $page
            ->where('generation.status', 'failed')
            ->where('generation.actual_cost', 0)
            ->where('generation.error_message', 'The prompt was rejected.'));
});

it('lists the user\'s recent generations in the sidebar, newest first', function () {
    $newer = Generation::factory()->for($this->user)->create(['status' => GenerationStatus::Processing]);
    Generation::factory()->create();

    $this->actingAs($this->user)
        ->get(route('generations.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentGenerations', 2)
            ->where('recentGenerations.0', ['id' => $newer->id, 'prompt' => $newer->prompt, 'status' => 'processing'])
            ->where('recentGenerations.1.id', $this->generation->id));
});

it('keeps the sidebar list to the 30 most recent generations', function () {
    Generation::factory()->for($this->user)->count(30)->create();

    $this->actingAs($this->user)
        ->get(route('generations.create'))
        ->assertInertia(fn (Assert $page) => $page->has('recentGenerations', 30));
});
