<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\BudgetQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(private readonly BudgetQueryService $budgetQueryService) {}

    public function index(Request $request): Response
    {
        $q = $request->string('q')->trim()->toString();
        $account = $request->string('account')->toString() ?: null;

        $results = [];
        $totalCount = 0;

        if (strlen($q) >= 2) {
            $data = $this->budgetQueryService->queryTransactions([
                'q' => $q,
                'account_type' => $account,
                'limit' => 50,
            ]);
            $results = $data['transactions'];
            $totalCount = $data['totalCount'];
        }

        $allCategories = Category::whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'icon'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
                'icon' => $c->icon,
            ])
            ->toArray();

        return Inertia::render('search', [
            'results' => $results,
            'totalCount' => $totalCount,
            'query' => $q,
            'currentAccount' => $account,
            'allCategories' => $allCategories,
        ]);
    }
}
