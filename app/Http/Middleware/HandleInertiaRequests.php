<?php

namespace App\Http\Middleware;

use App\Models\Account;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
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
            // Shared across all pages for the header AccountPicker (trivial query: select distinct type)
            'accountContexts' => $request->user()
                ? Account::query()
                    ->select('type')
                    ->distinct()
                    ->orderBy('type')
                    ->get()
                    ->map(fn ($a) => [
                        'value' => $a->type,
                        'label' => ucfirst($a->type),
                    ])
                    ->values()
                    ->toArray()
                : [],
        ];
    }
}
