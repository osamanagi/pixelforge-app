<?php

namespace App\Http\Controllers;

use App\Actions\CreateGeneration;
use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Http\Requests\EstimateGenerationPriceRequest;
use App\Http\Requests\StoreGenerationRequest;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('generations/Create', [
            'sizes' => array_column(GenerationSize::cases(), 'value'),
            'qualities' => array_column(GenerationQuality::cases(), 'value'),
        ]);
    }

    public function store(StoreGenerationRequest $request, CreateGeneration $createGeneration): RedirectResponse
    {
        $createGeneration->handle(
            $request->user(),
            $request->validated('prompt'),
            $request->enum('size', GenerationSize::class),
            $request->enum('quality', GenerationQuality::class),
            $request->referenceImages(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Generation submitted.')]);

        return to_route('generations.create');
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
