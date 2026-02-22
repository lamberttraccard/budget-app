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

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthParam)) {
            $monthParam = now()->format('Y-m');
        }

        $accountParam = $request->query('account', 'personal');
        if (! in_array($accountParam, ['personal', 'professional'], true)) {
            $accountParam = 'personal';
        }

        [$year, $month] = array_map('intval', explode('-', $monthParam));

        $data = $this->budgetQueryService->getMonthlyData($year, $month, $accountParam);

        $categoryTotals = array_map(function ($category) {
            $category['trendPercentage'] = null;

            return $category;
        }, $data['categoryTotals']);

        return Inertia::render('dashboard', [
            'categoryTotals' => $categoryTotals,
            'incomeCents' => $data['incomeCents'],
            'expensesCents' => $data['expensesCents'],
            'currentMonth' => $monthParam,
            'currentAccount' => $accountParam,
            'availableMonths' => $this->budgetQueryService->getAvailableMonths($accountParam),
            'importPreview' => session('importPreview'),
            'trendData' => Inertia::defer(fn () => $this->computeTrendData($year, $month, $accountParam, $data['categoryTotals']), 'trends'),
            'imports' => Inertia::defer(fn () => $this->getImportRecords(), 'imports'),
        ]);
    }

    private function computeTrendData(int $year, int $month, string $account, array $currentTotals): array
    {
        $prevMonth = $month === 1 ? 12 : $month - 1;
        $prevYear = $month === 1 ? $year - 1 : $year;
        $previousData = $this->budgetQueryService->getMonthlyData($prevYear, $prevMonth, $account);

        $hasPreviousMonthData = count($previousData['categoryTotals']) > 0;

        $previousAmounts = collect($previousData['categoryTotals'])
            ->keyBy('slug')
            ->map(fn ($cat) => $cat['amountCents']);

        $categoryTrends = [];
        foreach ($currentTotals as $category) {
            $prevAmount = $previousAmounts->get($category['slug']);
            $categoryTrends[$category['slug']] = $this->calculateTrendPercentage(
                $category['amountCents'],
                $prevAmount
            );
        }

        return [
            'hasPreviousMonthData' => $hasPreviousMonthData,
            'categoryTrends' => $categoryTrends,
        ];
    }

    private function getImportRecords(): array
    {
        return Import::where('status', 'completed')
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
            ])
            ->toArray();
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
