<?php

namespace App\Http\Controllers;

use App\Exceptions\CsvParseException;
use App\Exceptions\UnrecognizedCsvFormatException;
use App\Models\Account;
use App\Parsers\CsvParserFactory;
use App\Services\CsvImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
        ]);

        $file = $request->file('csv_file');
        $content = $file->get();
        $filename = $file->getClientOriginalName();

        try {
            $parser = $this->parserFactory->detect($content, $filename);
        } catch (UnrecognizedCsvFormatException|CsvParseException $e) {
            return back()->withErrors(['csv_file' => $e->getMessage()]);
        }

        $account = Account::firstOrCreate(
            ['bank' => $parser->bankName(), 'type' => 'personal'],
            ['name' => $parser->bankName().' Personal'],
        );

        try {
            $this->csvImportService->import($content, $filename, $account->id);
        } catch (UnrecognizedCsvFormatException|CsvParseException $e) {
            return back()->withErrors(['csv_file' => $e->getMessage()]);
        }

        return redirect()->route('dashboard');
    }
}
