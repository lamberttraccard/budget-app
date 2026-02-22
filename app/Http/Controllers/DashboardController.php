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

        return Inertia::render('dashboard', [
            'categoryTotals' => $data['categoryTotals'],
            'incomeCents' => $data['incomeCents'],
            'expensesCents' => $data['expensesCents'],
            'currentMonth' => $monthParam,
            'currentAccount' => $accountParam,
            'availableMonths' => $this->budgetQueryService->getAvailableMonths($accountParam),
            'importPreview' => session('importPreview'),
            'averageData' => Inertia::defer(fn () => $this->computeAverageData($accountParam), 'averages'),
            'imports' => Inertia::defer(fn () => $this->getImportRecords(), 'imports'),
        ]);
    }

    private function computeAverageData(string $accountType): ?array
    {
        $averages = $this->budgetQueryService->getMonthlyAverages($accountType);

        if ($averages['completeMonthCount'] < 2) {
            return null;
        }

        return $averages;
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

}
