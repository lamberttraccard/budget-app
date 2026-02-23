<?php

namespace App\Http\Middleware;

use App\Http\Concerns\ParsesAccountIds;
use App\Models\Account;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    use ParsesAccountIds;

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
            'flash' => [
                'updated_count' => fn () => $request->session()->get('updated_count'),
            ],
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'accountContexts' => $request->user()
                ? Account::query()
                    ->select('id', 'name', 'bank')
                    ->orderBy('name')
                    ->get()
                    ->map(fn ($a) => [
                        'id' => $a->id,
                        'name' => $a->name,
                        'bank' => $a->bank,
                    ])
                    ->values()
                    ->toArray()
                : [],
            'selectedAccountIds' => fn () => $this->parseAccountIds($request) ?? [],
            'allCategories' => fn () => $request->user()
                ? Category::whereNull('parent_id')
                    ->orderBy('name')
                    ->get(['id', 'name', 'color', 'icon'])
                    ->map(fn ($c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'color' => $c->color,
                        'icon' => $c->icon,
                    ])
                    ->toArray()
                : [],
        ];
    }
}
