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

        $accountIds = $this->parseAccountIds($request);

        [$year, $month] = array_map('intval', explode('-', $monthParam));

        $data = $this->budgetQueryService->getMonthlyData($year, $month, $accountIds);

        return Inertia::render('dashboard', [
            'categoryTotals' => $data['categoryTotals'],
            'incomeCents' => $data['incomeCents'],
            'expensesCents' => $data['expensesCents'],
            'currentMonth' => $monthParam,
            'selectedAccountIds' => $accountIds ?? [],
            'availableMonths' => $this->budgetQueryService->getAvailableMonths($accountIds),
            'importPreview' => session('importPreview'),
            'averageData' => Inertia::defer(fn () => $this->computeAverageData($accountIds), 'averages'),
            'sparklineData' => Inertia::defer(fn () => $this->budgetQueryService->getMonthlyExpenseAndIncomeTotals($accountIds), 'averages'),
            'imports' => Inertia::defer(fn () => $this->getImportRecords(), 'imports'),
        ]);
    }

    private function computeAverageData(?array $accountIds): ?array
    {
        $averages = $this->budgetQueryService->getMonthlyAverages($accountIds);

        if ($averages['completeMonthCount'] < 2) {
            return null;
        }

        return $averages;
    }

    private function getImportRecords(): array
    {
        return Import::whereIn('status', ['completed', 'categorizing', 'categorized'])
            ->withCount(['transactions as uncategorized_count' => fn ($q) => $q->whereNull('category_id')])
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
                'status' => $import->status,
                'uncategorizedCount' => $import->uncategorized_count,
                'batchId' => $import->batch_id,
            ])
            ->toArray();
    }
}
