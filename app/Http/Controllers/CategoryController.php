<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\BudgetQueryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(private readonly BudgetQueryService $budgetQueryService) {}

    public function show(Category $category, Request $request): Response
    {
        $monthParam = $request->query('month', now()->format('Y-m'));

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthParam)) {
            $monthParam = now()->format('Y-m');
        }

        $accountIds = $this->parseAccountIds($request);

        [$year, $month] = array_map('intval', explode('-', $monthParam));

        $data = $this->budgetQueryService->getCategoryDetail($category->id, $year, $month, $accountIds);

        $chartMonth = $request->query('chart_month', $monthParam);
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $chartMonth)) {
            $chartMonth = $monthParam;
        }

        [$chartYear, $chartMo] = array_map('intval', explode('-', $chartMonth));
        $fromMonth = Carbon::create($chartYear, $chartMo, 1)->subMonths(11)->format('Y-m');
        $monthlyTotals = $this->budgetQueryService->getCategoryMonthlyTotals($category->id, $fromMonth, $chartMonth, $accountIds);

        return Inertia::render('categories/show', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
                'slug' => $category->slug,
                'icon' => $category->icon,
            ],
            'subcategories' => $data['subcategories'],
            'transactions' => Inertia::defer(fn () => $data['transactions']),
            'totalAmountCents' => $data['totalAmountCents'],
            'transactionCount' => $data['transactionCount'],
            'currentMonth' => $monthParam,
            'selectedAccountIds' => $accountIds ?? [],
            'monthlyTotals' => $monthlyTotals,
            'chartMonth' => $chartMonth,
        ]);
    }
}
