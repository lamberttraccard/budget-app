# Story 2.6: Import User Interface & Confirmation

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As Lambert,
I want to import CSV files via drag-and-drop on desktop or file picker on mobile with a confirmation step,
so that I can easily add new financial data and verify before committing.

## Acceptance Criteria

1. **Given** I am on the Dashboard with no transactions, **When** the page renders, **Then** I see the EmptyState component: a centered invitation with a subtle upload icon and "Import your first CSV" prompt, with a tappable import area that opens the file picker (FR6).

2. **Given** I am on desktop and drag a CSV file over the window, **When** the file enters the drop zone, **Then** a full-page dashed border overlay appears with "Drop CSV to import" text. **When** the file leaves the drop zone or is dropped, **Then** the overlay disappears (FR6).

3. **Given** I am on mobile, **When** I tap the import button (below the category list), **Then** the native file picker opens filtered to CSV files (FR6).

4. **Given** I drop or select a valid CSV file, **When** the file is uploaded for preview, **Then** a confirmation Dialog (shadcn/ui) appears showing: detected bank source, total transaction count, and date range (FR34).

5. **Given** the confirmation dialog is showing, **When** I tap "Import", **Then** transactions are committed via `CsvImportService`, the dialog dismisses, and the Dashboard re-renders with updated category amounts and summary widget.

6. **Given** the confirmation dialog is showing, **When** I tap "Cancel", **Then** the dialog dismisses and no transactions are imported.

7. **Given** I drop or select an unrecognized file format, **When** parsing fails, **Then** a clear error message appears inline: "Unrecognized CSV format. Expected: Banque Populaire or Revolut export." (FR7). No dialog opens.

## Tasks / Subtasks

- [x] Task 1: Create ImportController with preview and store (AC: 4, 5, 7)
  - [x] 1.1: Create `app/Http/Controllers/ImportController.php`
  - [x] 1.2: Inject `CsvParserFactory` and `CsvImportService` via constructor
  - [x] 1.3: Implement `preview(Request $request)` — validates uploaded file, parses with CsvParserFactory, flashes preview data to session, redirects back to dashboard
  - [x] 1.4: Implement `store(Request $request)` — validates uploaded file, auto-finds/creates account, calls CsvImportService::import(), redirects to dashboard
  - [x] 1.5: Catch `UnrecognizedCsvFormatException` in both methods and redirect back with validation error `csv_file`
  - [x] 1.6: Catch `CsvParseException` in both methods and redirect back with validation error `csv_file`

- [x] Task 2: Update routes/web.php (AC: 4, 5)
  - [x] 2.1: Add `POST /imports/preview` route → `ImportController@preview`, auth middleware
  - [x] 2.2: Add `POST /imports` route → `ImportController@store`, auth middleware
  - [x] 2.3: Add `use App\Http\Controllers\ImportController;` import

- [x] Task 3: Update DashboardController to pass importPreview (AC: 4)
  - [x] 3.1: In `index()`, check `session('importPreview')` and include as Inertia prop if present
  - [x] 3.2: The prop should be `'importPreview' => session('importPreview')` (null when no preview)

- [x] Task 4: Add TypeScript types (AC: 4)
  - [x] 4.1: Add `ImportPreview` type to `resources/js/types/budget.ts`
  - [x] 4.2: Update `DashboardProps` to include optional `importPreview`

- [x] Task 5: Create EmptyState component (AC: 1)
  - [x] 5.1: Create `resources/js/components/EmptyState.tsx`
  - [x] 5.2: Props: `{ onImportClick: () => void }`
  - [x] 5.3: Render centered content: upload icon (lucide-react `Upload`), "Import your first CSV" prompt, tappable area

- [x] Task 6: Create ImportDropZone overlay component (AC: 2)
  - [x] 6.1: Create `resources/js/components/ImportDropZone.tsx`
  - [x] 6.2: Props: `{ visible: boolean }`
  - [x] 6.3: Render fixed overlay with dashed border and "Drop CSV to import" text

- [x] Task 7: Update Dashboard.tsx with full import flow (AC: 1–7)
  - [x] 7.1: Add state: `file`, `isDragging`, `showConfirmation`, `isUploading`, `importError`
  - [x] 7.2: Add hidden `<input type="file" accept=".csv">` with ref
  - [x] 7.3: Add drag-and-drop event handlers on the page wrapper
  - [x] 7.4: Implement `handleFileSelect` → POST to `/imports/preview` via `router.post` with `preserveState: true`
  - [x] 7.5: Implement `handleConfirmImport` → POST to `/imports` via `router.post`
  - [x] 7.6: Integrate EmptyState (when categoryTotals empty) with import trigger
  - [x] 7.7: Add import button below category list for subsequent imports
  - [x] 7.8: Add confirmation Dialog using importPreview prop
  - [x] 7.9: Add error display for import errors using `usePage().props.errors`
  - [x] 7.10: Add ImportDropZone overlay for desktop drag-and-drop

- [x] Task 8: Write Feature Tests for ImportController (AC: 1–7)
  - [x] 8.1: Create `tests/Feature/ImportControllerTest.php`
  - [x] 8.2: Test preview with valid BP CSV returns redirect with importPreview session data
  - [x] 8.3: Test preview with valid Revolut CSV returns correct bank name
  - [x] 8.4: Test preview with unrecognized CSV returns validation error on csv_file
  - [x] 8.5: Test store with valid CSV creates Import record and transactions
  - [x] 8.6: Test store auto-creates account when none exists for detected bank
  - [x] 8.7: Test store uses existing account when one matches the detected bank
  - [x] 8.8: Test store with unrecognized CSV returns validation error
  - [x] 8.9: Test both endpoints require authentication (redirect to login)
  - [x] 8.10: Test store fires ImportCompleted event
  - [x] 8.11: Test store with duplicate CSV imports zero new transactions gracefully

- [x] Task 9: Run Full Test Suite (AC: all)
  - [x] 9.1: Run `sail artisan test` — all existing + new tests pass with zero regressions
  - [x] 9.2: Verify TypeScript compiles cleanly: `sail bun run build`

## Dev Notes

### Architecture Overview for This Story

This story builds the UI layer on top of the existing import pipeline (Stories 2.1–2.4). The backend infrastructure — `CsvParserFactory`, `CsvImportService`, `DeduplicationService`, `CategorizationService`, and the `ImportCompleted` event pipeline — already exists and is fully tested. This story creates the HTTP controller, routes, and React components to expose that pipeline to the user.

```
User drops/picks CSV file
  │
  ├─ Step 1: Preview (POST /imports/preview)
  │   ├─ ImportController@preview
  │   │   ├─ Validate file (required, max 5MB)
  │   │   ├─ Read content: $file->get()
  │   │   ├─ CsvParserFactory::detect() → identifies bank
  │   │   ├─ Parser::parse() → counts transactions, extracts dates
  │   │   ├─ session()->flash('importPreview', [...])
  │   │   └─ return redirect()->back()
  │   │
  │   └─ DashboardController@index receives flashed data
  │       └─ Passes importPreview as Inertia prop
  │
  ├─ Step 2: Confirm (Dialog shown with preview data)
  │   └─ User clicks "Import" or "Cancel"
  │
  └─ Step 3: Import (POST /imports)
      └─ ImportController@store
          ├─ Validate file (required, max 5MB)
          ├─ Detect bank → Account::firstOrCreate(...)
          ├─ CsvImportService::import($content, $filename, $accountId)
          │   ├─ Parse → Dedup → Bulk insert → ImportCompleted event
          │   └─ ImportCompleted → CategorizeImportedTransactions listener
          │       ├─ MerchantMatchingService (instant, rule-based)
          │       └─ CategorizeTransactions job (AI/Gemini for unknowns)
          └─ return redirect()->route('dashboard')
```

**Key design decisions:**
- **Two requests (preview + import):** The file is uploaded twice — once for preview, once for import. This avoids temp file management complexity. CSV files are tiny (< 1MB typically), so the double upload is negligible.
- **`preserveState: true`** on the preview POST preserves the React component state (including the File object in `useState`) across the Inertia redirect, enabling the second upload without the user re-selecting the file.
- **Auto-account creation:** `Account::firstOrCreate(['bank' => $bankName, 'type' => 'personal'])` creates accounts automatically on first import from each bank. Story 3.3 will add account management and personal/professional selection.
- **No success toast:** Per UX spec: "Import success is communicated by the category amounts updating. No 'Import successful!' banner." The Dashboard re-rendering with new data IS the success feedback.

### ImportController Implementation

```php
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

        // Detect bank to auto-find/create account
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
```

**Key implementation notes:**
- File validation uses `'max:5120'` (5MB) — more than enough for any CSV export.
- No mime type validation (`'mimetypes:text/csv'`) because CSV MIME types are inconsistent across browsers and OSes. Some send `text/csv`, others `application/vnd.ms-excel`, others `application/octet-stream`. Relying on `CsvParserFactory::detect()` for format validation is more reliable.
- The `preview()` method catches parse exceptions and returns them as validation errors on `csv_file`, which Inertia exposes via `usePage().props.errors.csv_file`.
- `Account::firstOrCreate` uses `['bank' => ..., 'type' => 'personal']` as the lookup key, and `['name' => ... 'Personal']` as defaults. This means the first import from each bank auto-creates a personal account.

### DashboardController Update

```php
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
        'importPreview'  => session('importPreview'),  // NEW — null when no preview
    ]);
}
```

**Only one line added.** The `session('importPreview')` returns the flashed data (set by `ImportController@preview`) or `null` if no preview was requested. Since session flash is consumed on read, it won't persist to subsequent requests.

### Routes Update

```php
// routes/web.php — add after the dashboard route:
Route::post('imports/preview', [ImportController::class, 'preview'])
    ->middleware(['auth', 'verified'])
    ->name('imports.preview');

Route::post('imports', [ImportController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('imports.store');
```

Add at top: `use App\Http\Controllers\ImportController;`

### TypeScript Types

Add to `resources/js/types/budget.ts`:

```typescript
export type ImportPreview = {
    bank: string;           // "Banque Populaire" or "Revolut"
    transactionCount: number;
    dateFrom: string;       // ISO date "2026-01-01"
    dateTo: string;         // ISO date "2026-01-31"
    fileName: string;
};
```

Update `DashboardProps`:

```typescript
export type DashboardProps = {
    categoryTotals: CategoryTotal[];
    incomeCents: number;
    expensesCents: number;
    currentMonth: string;
    importPreview?: ImportPreview | null;  // NEW
};
```

### EmptyState Component

```tsx
// resources/js/components/EmptyState.tsx
import { UploadIcon } from 'lucide-react';

type Props = {
    onImportClick: () => void;
};

export default function EmptyState({ onImportClick }: Props) {
    return (
        <button
            type="button"
            onClick={onImportClick}
            className="w-full rounded-xl border-2 border-dashed border-zinc-700 bg-surface p-8 text-center transition-colors hover:border-zinc-500 hover:bg-surface-elevated"
        >
            <UploadIcon className="mx-auto mb-3 size-8 text-text-tertiary" />
            <p className="text-sm font-medium text-white">Import your first CSV</p>
            <p className="mt-1 text-xs text-text-tertiary">
                Drag & drop or tap to select a Banque Populaire or Revolut export
            </p>
        </button>
    );
}
```

**Design notes:**
- The entire component is a `<button>` so it's a single tap target (44px+ height).
- Dashed border signals "drop zone" visually, matching the UX spec's "clean invitation to import."
- Uses existing design tokens: `bg-surface`, `text-text-tertiary`, `border-zinc-700`.
- Hover state shows `bg-surface-elevated` and brighter border.

### ImportDropZone Overlay Component

```tsx
// resources/js/components/ImportDropZone.tsx

type Props = {
    visible: boolean;
};

export default function ImportDropZone({ visible }: Props) {
    if (!visible) return null;

    return (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-background/80 backdrop-blur-sm">
            <div className="rounded-2xl border-2 border-dashed border-zinc-500 px-12 py-10 text-center">
                <p className="text-lg font-semibold text-white">Drop CSV to import</p>
                <p className="mt-1 text-sm text-text-secondary">
                    Banque Populaire or Revolut export
                </p>
            </div>
        </div>
    );
}
```

**Design notes:**
- Full-screen fixed overlay at z-40 (below the Dialog at z-50).
- Semi-transparent background with backdrop blur for focus effect.
- Dashed border centered box with clear instruction text.
- `visible` prop controls rendering — no CSS transitions for MVP simplicity.

### Dashboard.tsx Full Implementation

```tsx
// resources/js/pages/dashboard.tsx
import { Head, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { UploadIcon } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import SummaryWidget from '@/components/SummaryWidget';
import CategoryCard from '@/components/CategoryCard';
import EmptyState from '@/components/EmptyState';
import ImportDropZone from '@/components/ImportDropZone';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import type { CategoryTotal, DashboardProps } from '@/types';

function formatMonthLabel(month: string): string {
    const [year, m] = month.split('-');
    const date = new Date(Number(year), Number(m) - 1);
    return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
}

function formatDateRange(from: string, to: string): string {
    const opts: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric' };
    const fromDate = new Date(from + 'T00:00:00');
    const toDate = new Date(to + 'T00:00:00');
    return `${fromDate.toLocaleDateString('en-US', opts)} – ${toDate.toLocaleDateString('en-US', { ...opts, year: 'numeric' })}`;
}

export default function Dashboard({
    categoryTotals,
    incomeCents,
    expensesCents,
    currentMonth,
    importPreview,
}: DashboardProps) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [isDragging, setIsDragging] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [showConfirmation, setShowConfirmation] = useState(false);

    // Show confirmation dialog when importPreview arrives from server
    useEffect(() => {
        if (importPreview && file) {
            setShowConfirmation(true);
            setIsUploading(false);
        }
    }, [importPreview]);

    const triggerFileInput = useCallback(() => {
        fileInputRef.current?.click();
    }, []);

    const handleFileSelect = useCallback((selectedFile: File) => {
        setFile(selectedFile);
        setIsUploading(true);

        router.post('/imports/preview', { csv_file: selectedFile }, {
            preserveState: true,
            preserveScroll: true,
            onError: () => {
                setIsUploading(false);
                setFile(null);
            },
        });
    }, []);

    const handleInputChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
        const selectedFile = e.target.files?.[0];
        if (selectedFile) {
            handleFileSelect(selectedFile);
        }
        // Reset input so the same file can be selected again
        e.target.value = '';
    }, [handleFileSelect]);

    const handleConfirmImport = useCallback(() => {
        if (!file) return;
        setIsUploading(true);

        router.post('/imports', { csv_file: file }, {
            onSuccess: () => {
                setFile(null);
                setShowConfirmation(false);
                setIsUploading(false);
            },
            onError: () => {
                setShowConfirmation(false);
                setIsUploading(false);
                setFile(null);
            },
        });
    }, [file]);

    const handleCancelImport = useCallback(() => {
        setShowConfirmation(false);
        setFile(null);
    }, []);

    // --- Drag-and-drop handlers (desktop) ---
    const handleDragEnter = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(true);
    }, []);

    const handleDragLeave = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        // Only hide overlay if leaving the page entirely (not entering a child)
        if (e.currentTarget.contains(e.relatedTarget as Node)) return;
        setIsDragging(false);
    }, []);

    const handleDragOver = useCallback((e: React.DragEvent) => {
        e.preventDefault(); // Required to enable drop
        e.stopPropagation();
    }, []);

    const handleDrop = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);

        const droppedFile = e.dataTransfer.files[0];
        if (droppedFile) {
            handleFileSelect(droppedFile);
        }
    }, [handleFileSelect]);

    return (
        <AppLayout>
            <Head title="Budget" />

            {/* Hidden file input */}
            <input
                ref={fileInputRef}
                type="file"
                accept=".csv,text/csv"
                className="hidden"
                onChange={handleInputChange}
            />

            {/* Drag-and-drop zone wraps the entire page content */}
            <div
                className="py-6 space-y-5"
                onDragEnter={handleDragEnter}
                onDragLeave={handleDragLeave}
                onDragOver={handleDragOver}
                onDrop={handleDrop}
            >
                {/* Import error display */}
                {errors.csv_file && (
                    <div className="rounded-xl bg-red-500/10 border border-red-500/20 p-4">
                        <p className="text-sm text-red-400">{errors.csv_file}</p>
                    </div>
                )}

                <SummaryWidget incomeCents={incomeCents} expensesCents={expensesCents} />

                {categoryTotals.length === 0 ? (
                    <EmptyState onImportClick={triggerFileInput} />
                ) : (
                    <>
                        <div className="space-y-3">
                            {categoryTotals.map((cat: CategoryTotal) => (
                                <CategoryCard
                                    key={cat.id}
                                    name={cat.name}
                                    color={cat.color}
                                    amountCents={cat.amountCents}
                                    transactionCount={cat.transactionCount}
                                />
                            ))}
                        </div>

                        {/* Inline import button for subsequent imports */}
                        <button
                            type="button"
                            onClick={triggerFileInput}
                            disabled={isUploading}
                            className="flex w-full items-center justify-center gap-2 rounded-xl border border-zinc-800 bg-surface p-3 text-sm text-text-secondary transition-colors hover:border-zinc-600 hover:text-white disabled:opacity-50"
                        >
                            <UploadIcon className="size-4" />
                            {isUploading ? 'Uploading...' : 'Import CSV'}
                        </button>
                    </>
                )}
            </div>

            {/* Desktop drag-and-drop overlay */}
            <ImportDropZone visible={isDragging} />

            {/* Import confirmation dialog */}
            <Dialog open={showConfirmation} onOpenChange={(open) => {
                if (!open) handleCancelImport();
            }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Import transactions</DialogTitle>
                        <DialogDescription>
                            Review the import summary before committing.
                        </DialogDescription>
                    </DialogHeader>

                    {importPreview && (
                        <div className="space-y-3 py-2">
                            <div className="flex justify-between text-sm">
                                <span className="text-text-secondary">Bank</span>
                                <span className="font-medium text-white">{importPreview.bank}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-text-secondary">Transactions</span>
                                <span className="font-medium text-white">{importPreview.transactionCount}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-text-secondary">Period</span>
                                <span className="font-medium text-white">
                                    {formatDateRange(importPreview.dateFrom, importPreview.dateTo)}
                                </span>
                            </div>
                        </div>
                    )}

                    <DialogFooter>
                        <Button variant="outline" onClick={handleCancelImport} disabled={isUploading}>
                            Cancel
                        </Button>
                        <Button onClick={handleConfirmImport} disabled={isUploading}>
                            {isUploading ? 'Importing...' : 'Import'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
```

**Critical implementation notes:**

1. **`preserveState: true`** on the preview POST is essential. It preserves the React component's `useState` values (including the `File` object) across the Inertia redirect. Without it, the File would be lost when the page re-renders with importPreview props.

2. **`useEffect` on `importPreview`** opens the confirmation dialog when the server returns preview data. The `file` check prevents the dialog from opening on page load when importPreview is null.

3. **Drag-and-drop wraps `py-6 space-y-5`** (the content area), NOT the entire page. This keeps drag events within the content bounds. The ImportDropZone overlay is rendered outside this wrapper as a fixed overlay.

4. **`handleDragLeave` child element fix:** `e.currentTarget.contains(e.relatedTarget)` prevents the overlay from flickering when the cursor moves over child elements within the drop zone.

5. **Error display:** Import errors from `usePage().props.errors.csv_file` appear inline above the SummaryWidget. These are set by the controller via `back()->withErrors(...)`.

6. **File input `accept=".csv,text/csv"`** provides OS-level file filtering but is NOT relied upon for validation (server validates format via CsvParserFactory).

7. **File input reset:** `e.target.value = ''` after reading the file ensures the `onChange` event fires even if the user selects the same file again.

8. **`isUploading` state** disables the import button and shows "Uploading..." / "Importing..." text during network requests.

### Testing Strategy

**Feature tests in `tests/Feature/ImportControllerTest.php`:**

All tests use `RefreshDatabase` trait and seed `CategorySeeder` (needed because `CsvImportService` triggers categorization which queries categories). Use `Event::fake([ImportCompleted::class])` where you want to prevent the categorization side effects.

**Test CSV content helpers:**

```php
private function bpCsvContent(): string
{
    return "Compte;Date de comptabilisation;Date operation;Libelle;Reference;Date valeur;Montant\r\n" .
           "00012345678;01/02/2026;01/02/2026;CARREFOUR CITY;REF001;01/02/2026;-42,50\r\n" .
           "00012345678;05/02/2026;05/02/2026;SNCF INTERNET;REF002;05/02/2026;-85,00\r\n" .
           "00012345678;10/02/2026;10/02/2026;VIREMENT SALAIRE;REF003;10/02/2026;3200,00";
}

private function revolutCsvContent(): string
{
    return "Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance\n" .
           "CARD_PAYMENT,Current,2026-02-01 10:00:00,2026-02-01 10:00:00,Amazon,-25.99,0.00,EUR,COMPLETED,1000.00\n" .
           "CARD_PAYMENT,Current,2026-02-15 14:30:00,2026-02-15 14:30:00,Netflix,-13.99,0.00,EUR,COMPLETED,986.01";
}
```

**Sample test for preview endpoint:**

```php
public function test_preview_with_valid_bp_csv_flashes_import_preview(): void
{
    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());

    $response = $this->actingAs($user)
        ->post(route('imports.preview'), ['csv_file' => $file]);

    $response->assertRedirect();
    $response->assertSessionHas('importPreview', function (array $preview) {
        return $preview['bank'] === 'Banque Populaire'
            && $preview['transactionCount'] === 3
            && $preview['dateFrom'] === '2026-02-01'
            && $preview['dateTo'] === '2026-02-10'
            && $preview['fileName'] === 'releve.csv';
    });
}
```

**Sample test for store endpoint:**

```php
public function test_store_creates_import_and_transactions(): void
{
    $this->seed(CategorySeeder::class);
    Event::fake([ImportCompleted::class]);
    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());

    $response = $this->actingAs($user)
        ->post(route('imports.store'), ['csv_file' => $file]);

    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('imports', [
        'file_name' => 'releve.csv',
        'bank' => 'Banque Populaire',
        'status' => 'completed',
    ]);

    $this->assertDatabaseHas('accounts', [
        'bank' => 'Banque Populaire',
        'type' => 'personal',
        'name' => 'Banque Populaire Personal',
    ]);

    $this->assertDatabaseCount('transactions', 3);
    Event::assertDispatched(ImportCompleted::class);
}
```

**Sample test for unrecognized format:**

```php
public function test_preview_with_unrecognized_csv_returns_error(): void
{
    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('random.csv', "col1,col2,col3\nfoo,bar,baz");

    $response = $this->actingAs($user)
        ->post(route('imports.preview'), ['csv_file' => $file]);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['csv_file']);
}
```

**Sample test for auth guard:**

```php
public function test_preview_requires_authentication(): void
{
    $file = UploadedFile::fake()->createWithContent('releve.csv', $this->bpCsvContent());

    $this->post(route('imports.preview'), ['csv_file' => $file])
        ->assertRedirect(route('login'));
}
```

**Test notes:**
- Use `UploadedFile::fake()->createWithContent(name, content)` to create test CSV files with real parseable content. This is critical — `UploadedFile::fake()->create('file.csv')` creates an empty file that won't parse.
- Use `Event::fake([ImportCompleted::class])` in store tests to prevent the `CategorizeImportedTransactions` listener from running (it dispatches queue jobs and queries the AI).
- Test the preview session data shape carefully — the frontend depends on exact key names.

### Previous Story Intelligence

**From Story 2.5 (Financial Overview Dashboard — direct predecessor):**
- 187 tests, 644 assertions, zero regressions — maintain this standard
- `DashboardController` at `app/Http/Controllers/DashboardController.php` — needs one line added (importPreview prop)
- `Dashboard.tsx` at `resources/js/pages/dashboard.tsx` — needs significant rewrite to integrate import flow
- `SummaryWidget` and `CategoryCard` components exist and are unchanged
- `formatAmount.ts` and `formatAbsAmount.ts` utilities exist
- `DashboardProps` type in `resources/js/types/budget.ts` — needs ImportPreview addition
- `AppLayout` in `resources/js/layouts/app/app-layout.tsx` — NOT modified (import triggers live in Dashboard, not header)
- Category colors come from DB via Inertia props — never hardcode
- All commands via Sail: `sail artisan test`, `sail bun run build`

**From Story 2.4 (AI Auto-Categorization):**
- `ImportCompleted` event auto-triggers `CategorizeImportedTransactions` listener → runs `MerchantMatchingService` → dispatches `CategorizeTransactions` job for unknowns
- No extra work needed to trigger categorization — it happens automatically when `ImportCompleted` fires from `CsvImportService`
- 172 tests before Story 2.5, 187 after — expect ~197+ after this story

**From Story 2.3 (Import Pipeline):**
- `CsvImportService::import(string $content, string $filename, int $accountId): Import` — takes raw CSV content string (NOT a file path), filename for record-keeping, and account_id
- The controller must call `$file->get()` to read the uploaded file content
- `DeduplicationService` handles duplicates silently — reimporting the same file produces zero new transactions with no errors

**From Story 2.1 (CSV Parser Framework):**
- `CsvParserFactory::detect()` throws `UnrecognizedCsvFormatException` with a human-readable message
- `CsvParserInterface::parse()` returns `Collection<TransactionData>` where `TransactionData` has `date`, `description`, `amountCents`, `rawLine`
- Each parser implements `bankName(): string` — returns "Banque Populaire" or "Revolut"

### Git Intelligence

Recent commits (one per story):
- `2d82942` Story 2.5: Financial overview dashboard with BudgetQueryService and review fixes
- `0035160` Story 2.4: AI auto-categorization engine with hybrid merchant matching + Gemini pipeline
- `a37277f` Story 2.3: Import pipeline with SHA-256 deduplication

**Established patterns:**
- One commit per story
- All commands via Sail (`sail artisan test`, `sail bun run build`)
- Tests must pass with zero regressions before committing
- Feature tests: use `RefreshDatabase` trait + seed required data
- Test file placement: `tests/Feature/` for HTTP and DB-dependent tests

### Architecture Compliance

| Requirement | Implementation |
|-------------|----------------|
| Inertia for web UI [arch#API Patterns] | `ImportController` returns `redirect()->back()` and `redirect()->route('dashboard')` — never JSON |
| Inertia props in camelCase [arch#Naming Patterns] | `importPreview` prop with camelCase keys: `transactionCount`, `dateFrom`, `dateTo`, `fileName` |
| Services are stateless [arch#Service Boundary] | `ImportController` delegates to `CsvImportService` — no business logic in controller |
| `router.post` for client→server [arch#Communication] | Both preview and import use `router.post()` — never raw fetch/axios |
| Amount handling [arch#Format Patterns] | Not directly handled in this story — amounts stay as integer cents in the pipeline |
| Error handling [arch#Process Patterns] | Custom exceptions caught in controller → `back()->withErrors()` → Inertia error bag → inline display |
| Naming [arch#Naming Patterns] | Controller: `ImportController`, Routes: `/imports`, `/imports/preview`, Method: `preview()`, `store()` |
| Category colors from DB [arch#Enforcement] | EmptyState and ImportDropZone don't reference category colors — no violation possible |

### Existing Components Used (No Modifications Needed)

| Component | File | Usage in This Story |
|-----------|------|-------------------|
| Dialog | `resources/js/components/ui/dialog.tsx` | Import confirmation dialog |
| Button | `resources/js/components/ui/button.tsx` | Dialog action buttons, import button |
| SummaryWidget | `resources/js/components/SummaryWidget.tsx` | Unchanged — rendered above import UI |
| CategoryCard | `resources/js/components/CategoryCard.tsx` | Unchanged — rendered in category list |

### What This Story Does NOT Include

- **MonthStrip component** — Story 3.2 (Month Navigation). The import just works on the current month.
- **Account picker / selection** — Story 3.3 (Account Management). Accounts are auto-created as personal.
- **Category drill-down / clickable cards** — Story 3.1. CategoryCard remains non-clickable.
- **Import deletion** — Story 4.3. No way to delete imports yet.
- **AI learning toast ("Got it, I'll remember")** — Story 4.2. Categorization happens in background via queue.
- **Upload progress bar** — Not in AC. The `isUploading` state shows "Uploading..." text, which is sufficient for small CSV files.

### Project Structure Notes

**New files to create:**
```
app/
├── Http/Controllers/
│   └── ImportController.php                    (NEW)
resources/js/
├── components/
│   ├── EmptyState.tsx                          (NEW)
│   └── ImportDropZone.tsx                      (NEW)
```

**Modified files:**
```
app/Http/Controllers/DashboardController.php    (add importPreview prop — 1 line)
routes/web.php                                  (add 2 import routes)
resources/js/pages/dashboard.tsx                (major rewrite — import flow integration)
resources/js/types/budget.ts                    (add ImportPreview type, update DashboardProps)
tests/Feature/ImportControllerTest.php          (NEW — ~10 tests)
```

**Aligns with architecture directory structure:**
- Controllers in `app/Http/Controllers/` (flat) [Source: architecture.md#Structure Patterns]
- Components in `resources/js/components/` (flat) [Source: architecture.md#Structure Patterns]
- Types in `resources/js/types/` [Source: architecture.md#Structure Patterns]

### References

- Epic 2 story requirements: [Source: _bmad-output/planning-artifacts/epics.md#Story 2.6]
- Import pipeline: [Source: app/Services/CsvImportService.php]
- CSV parser factory: [Source: app/Parsers/CsvParserFactory.php]
- Import confirmation UX: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Action Patterns]
- EmptyState design: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Component Strategy]
- ImportDropZone design: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Component Strategy]
- Dialog component: [Source: resources/js/components/ui/dialog.tsx]
- Drag-and-drop pattern: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Responsive Design]
- Error feedback pattern: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Feedback Patterns]
- DashboardController: [Source: app/Http/Controllers/DashboardController.php]
- Account model: [Source: app/Models/Account.php]
- Inertia file uploads: [Source: inertiajs.com/file-uploads — auto FormData conversion]
- Previous story 2.5: [Source: _bmad-output/implementation-artifacts/2-5-financial-overview-dashboard.md]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6

### Debug Log References

- Fixed test `store_uses_existing_account_when_one_matches`: Import model doesn't have `account_id` column — account linkage is at Transaction level. Changed assertion to verify transactions are linked to existing account via `$existingAccount->transactions()->exists()`.

### Completion Notes List

- Created `ImportController` with `preview()` and `store()` methods following two-request pattern (preview → confirm → import)
- Preview flashes session data with bank name, transaction count, date range, filename
- Store auto-creates Account via `firstOrCreate` keyed on bank+type
- Both methods catch `UnrecognizedCsvFormatException` and `CsvParseException`, returning validation errors on `csv_file`
- Added 2 POST routes with auth+verified middleware
- DashboardController passes `importPreview` session data as Inertia prop (1 line change)
- Added `ImportPreview` TypeScript type and updated `DashboardProps`
- Created `EmptyState` component — centered button with upload icon and "Import your first CSV" prompt
- Created `ImportDropZone` overlay — fixed full-page dashed border with "Drop CSV to import" text
- Rewrote `Dashboard.tsx` with complete import flow: drag-and-drop, file picker, preview POST with `preserveState: true`, confirmation Dialog, error display
- 12 feature tests covering: preview (BP, Revolut, unrecognized), store (valid, auto-create account, existing account, unrecognized, duplicate), auth guards, event dispatch, dashboard importPreview prop integration
- All 200 tests pass (13 new), 696 assertions, zero regressions
- TypeScript build compiles cleanly

### File List

**New files:**
- `app/Http/Controllers/ImportController.php`
- `resources/js/components/EmptyState.tsx`
- `resources/js/components/ImportDropZone.tsx`
- `tests/Feature/ImportControllerTest.php`

**Modified files:**
- `app/Http/Controllers/DashboardController.php` (added importPreview prop)
- `routes/web.php` (added 2 import routes + ImportController import)
- `resources/js/pages/dashboard.tsx` (full rewrite with import flow)
- `resources/js/types/budget.ts` (added ImportPreview type, updated DashboardProps)

### Change Log

- 2026-02-20: Story 2.6 implementation — Import UI with drag-and-drop, file picker, preview/confirmation dialog, and full backend controller with 11 feature tests
- 2026-02-20: Code review fixes — (1) Added `pointer-events-none` to ImportDropZone overlay to fix drag-and-drop flickering, (2) Fixed useEffect dependency array, (3) Added disabled state to EmptyState during upload, (4) Added integration test for DashboardController importPreview prop
