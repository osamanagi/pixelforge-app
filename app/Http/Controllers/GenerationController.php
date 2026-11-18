<?php

namespace App\Http\Controllers;

use App\Actions\CreateGeneration;
use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Http\Requests\EstimateGenerationPriceRequest;
use App\Http\Requests\StoreGenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Models\Generation;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function show(Generation $generation): Response
    {
        return Inertia::render('generations/Show', [
            'generation' => GenerationResource::make($generation),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('generations/Create', [
            'sizes' => array_column(GenerationSize::cases(), 'value'),
            'qualities' => array_column(GenerationQuality::cases(), 'value'),
        ]);
    }

    public function store(StoreGenerationRequest $request, CreateGeneration $createGeneration): RedirectResponse
    {
        $generation = $createGeneration->handle(
            $request->user(),
            $request->validated('prompt'),
            $request->enum('size', GenerationSize::class),
            $request->enum('quality', GenerationQuality::class),
            $request->referenceImages(),
        );

        return to_route('generations.show', $generation);
    }

    public function estimate(EstimateGenerationPriceRequest $request, PricingService $pricing): JsonResponse
    {
        return response()->json([
            'estimated_price' => $pricing->estimatedPrice(
                (string) $request->validated('prompt'),
                $request->enum('size', GenerationSize::class),
                $request->enum('quality', GenerationQuality::class),
                $request->integer('reference_image_count'),
            ),
        ]);
    }
}
