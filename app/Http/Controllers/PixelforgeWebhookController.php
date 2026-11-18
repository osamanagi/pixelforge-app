<?php

namespace App\Http\Controllers;

use App\Actions\HandlePixelforgeWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request, HandlePixelforgeWebhook $handleWebhook): Response
    {
        $request->validate([
            'id' => ['required', 'string'],
            'type' => ['required', 'string'],
            'data.object.id' => ['required_if:type,generation.processing,generation.succeeded,generation.failed', 'string'],
            'data.object.status' => ['required_if:type,generation.processing,generation.succeeded,generation.failed', 'string'],
        ]);

        $handleWebhook->handle($request->json()->all());

        return response()->noContent();
    }
}
