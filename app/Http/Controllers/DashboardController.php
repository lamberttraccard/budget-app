<?php

namespace App\Http\Controllers;

use App\Models\Import;
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

        $accountParam = $request->query('account', 'personal');
        if (!in_array($accountParam, ['personal', 'professional'], true)) {
            $accountParam = 'personal';
        }

        [$year, $month] = array_map('intval', explode('-', $monthParam));

        $data = $this->budgetQueryService->getMonthlyData($year, $month, $accountParam);

        $prevMonth = $month === 1 ? 12 : $month - 1;
        $prevYear = $month === 1 ? $year - 1 : $year;
        $previousData = $this->budgetQueryService->getMonthlyData($prevYear, $prevMonth, $accountParam);

        $hasPreviousMonthData = count($previousData['categoryTotals']) > 0;

        $previousAmounts = collect($previousData['categoryTotals'])
            ->keyBy('slug')
            ->map(fn ($cat) => $cat['amountCents']);

        $categoryTotals = array_map(function ($category) use ($previousAmounts) {
            $prevAmount = $previousAmounts->get($category['slug']);
            $category['trendPercentage'] = $this->calculateTrendPercentage(
                $category['amountCents'],
                $prevAmount
            );
            return $category;
        }, $data['categoryTotals']);

        $imports = Import::where('status', 'completed')
            ->orderByDesc('imported_at')
            ->get()
            ->map(fn (Import $import) => [
                'id' => $import->id,
                'fileName' => $import->file_name,
                'bank' => $import->bank,
                'transactionCount' => $import->transaction_count,
                'dateFrom' => $import->date_from?->toDateString(),
                'dateTo' => $import->date_to?->toDateString(),
                'importedAt' => $import->imported_at?->toDateString(),
            ]);

        return Inertia::render('dashboard', [
            'categoryTotals'        => $categoryTotals,
            'incomeCents'           => $data['incomeCents'],
            'expensesCents'         => $data['expensesCents'],
            'currentMonth'          => $monthParam,
            'currentAccount'        => $accountParam,
            'availableMonths'       => $this->budgetQueryService->getAvailableMonths($accountParam),
            'hasPreviousMonthData'  => $hasPreviousMonthData,
            'importPreview'         => session('importPreview'),
            'imports'               => $imports,
        ]);
    }

    private function calculateTrendPercentage(int $currentCents, ?int $previousCents): ?int
    {
        if ($previousCents === null) {
            return null;
        }

        if ($previousCents === 0) {
            return null;
        }

        $currentAbs = abs($currentCents);
        $previousAbs = abs($previousCents);

        return (int) round((($currentAbs - $previousAbs) / $previousAbs) * 100);
    }
}
