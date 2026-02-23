<?php

namespace App\Http\Controllers;

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
        $accountIds = $this->parseAccountIds($request);

        $results = [];
        $totalCount = 0;

        if (strlen($q) >= 2) {
            $data = $this->budgetQueryService->queryTransactions([
                'q' => $q,
                'account_ids' => $accountIds,
                'limit' => 50,
            ]);
            $results = $data['transactions'];
            $totalCount = $data['totalCount'];
        }

        return Inertia::render('search', [
            'results' => $results,
            'totalCount' => $totalCount,
            'query' => $q,
            'selectedAccountIds' => $accountIds ?? [],
        ]);
    }
}
