<?php

namespace App\Http\Controllers;

use App\Exceptions\CsvParseException;
use App\Exceptions\UnrecognizedCsvFormatException;
use App\Jobs\CategorizeTransactions;
use App\Models\Account;
use App\Models\Import;
use App\Parsers\CsvParserFactory;
use App\Services\CsvImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

class ImportController extends Controller
{
    public function __construct(
        private readonly CsvParserFactory $parserFactory,
        private readonly CsvImportService $csvImportService,
    ) {}

    public function preview(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'max:5120'],
        ]);

        $file = $request->file('csv_file');
        $content = $file->get();
        $filename = $file->getClientOriginalName();

        try {
            $parser = $this->parserFactory->detect($content, $filename);
            $transactions = $parser->parse($content);
        } catch (UnrecognizedCsvFormatException|CsvParseException $e) {
            return back()->withErrors(['csv_file' => $e->getMessage()]);
        }

        $dates = $transactions->map(fn ($t) => $t->date)->sort()->values();

        session()->flash('importPreview', [
            'bank' => $parser->bankName(),
            'transactionCount' => $transactions->count(),
            'dateFrom' => $dates->first(),
            'dateTo' => $dates->last(),
            'fileName' => $filename,
        ]);

        return back();
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'max:5120'],
            'account_id' => ['required_without:new_account_name', 'integer', 'exists:accounts,id'],
            'new_account_name' => ['required_without:account_id', 'string', 'max:255'],
        ]);

        $file = $request->file('csv_file');
        $content = $file->get();
        $filename = $file->getClientOriginalName();

        try {
            $parser = $this->parserFactory->detect($content, $filename);
        } catch (UnrecognizedCsvFormatException|CsvParseException $e) {
            return back()->withErrors(['csv_file' => $e->getMessage()]);
        }

        if ($request->filled('account_id')) {
            $account = Account::findOrFail($request->input('account_id'));
        } else {
            $account = Account::create([
                'name' => $request->input('new_account_name'),
                'bank' => $parser->bankName(),
            ]);
        }

        try {
            $this->csvImportService->import($content, $filename, $account->id);
        } catch (UnrecognizedCsvFormatException|CsvParseException $e) {
            return back()->withErrors(['csv_file' => $e->getMessage()]);
        }

        return redirect()->route('home');
    }

    public function status(Import $import): JsonResponse
    {
        $data = ['status' => $import->status];

        if ($import->batch_id) {
            $batch = Bus::findBatch($import->batch_id);

            if ($batch) {
                $data['progress'] = $batch->progress();
                $data['processedJobs'] = $batch->processedJobs();
                $data['totalJobs'] = $batch->totalJobs;
                $data['failedJobs'] = $batch->failedJobs;
                $data['finishedAt'] = $batch->finishedAt?->toISOString();
            }
        }

        return response()->json($data);
    }

    public function recategorize(Import $import): RedirectResponse
    {
        if ($import->isCategorizing()) {
            return back();
        }

        CategorizeTransactions::dispatch($import);

        return back();
    }

    public function destroy(Import $import): RedirectResponse
    {
        if ($import->batch_id) {
            Bus::findBatch($import->batch_id)?->cancel();
        }

        $import->delete();

        return back();
    }
}
