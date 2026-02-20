<?php

namespace App\Http\Controllers;

use App\Services\BudgetQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly BudgetQueryService $budgetQueryService) {}

    public function index(Request $request): Response
    {
        $monthParam = $request->query('month', now()->format('Y-m'));

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthParam)) {
            $monthParam = now()->format('Y-m');
        }

        [$year, $month] = array_map('intval', explode('-', $monthParam));

        $data = $this->budgetQueryService->getMonthlyData($year, $month);

        return Inertia::render('dashboard', [
            'categoryTotals' => $data['categoryTotals'],
            'incomeCents'    => $data['incomeCents'],
            'expensesCents'  => $data['expensesCents'],
            'currentMonth'   => $monthParam,
        ]);
    }
}
