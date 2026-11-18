<?php

namespace App\Http\Middleware;

use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    private const int RECENT_GENERATIONS_LIMIT = 30;

    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'recentGenerations' => fn () => $this->recentGenerations($request),
        ];
    }

    /**
     * @return array<int, array{id: int, prompt: string, status: string}>
     */
    private function recentGenerations(Request $request): array
    {
        if ($request->user() === null) {
            return [];
        }

        return $request->user()->generations()
            ->latest()
            ->orderByDesc('id')
            ->limit(self::RECENT_GENERATIONS_LIMIT)
            ->get(['id', 'prompt', 'status'])
            ->map(fn (Generation $generation): array => [
                'id' => $generation->id,
                'prompt' => Str::limit($generation->prompt, 60),
                'status' => $generation->status->value,
            ])
            ->all();
    }
}
