---
title: 'Batch Categorization with Progress Tracking'
slug: 'batch-categorization-progress'
created: '2026-02-22'
status: 'ready-for-dev'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['Laravel 12', 'Bus::batch()', 'React 19', 'Inertia.js 2', 'sonner (toast)', 'motion/react']
files_to_modify: ['app/Jobs/CategorizeTransactions.php', 'app/Jobs/CategorizeTransactionChunk.php (new)', 'app/Services/CategorizationService.php', 'app/Http/Controllers/ImportController.php', 'app/Http/Controllers/DashboardController.php', 'app/Models/Import.php', 'database/migrations/xxxx_add_batch_id_to_imports_table.php (new)', 'database/factories/ImportFactory.php', 'routes/web.php', 'resources/js/pages/dashboard.tsx', 'resources/js/components/ImportHistory.tsx', 'resources/js/components/CategorizationProgress.tsx (new)', 'resources/js/types/budget.ts', 'config/categorization.php', 'tests/Feature/CategorizeTransactionsTest.php', 'tests/Feature/CategorizationServiceTest.php', 'tests/Feature/ImportControllerTest.php']
code_patterns: ['Constructor injection for services', 'Events + Listeners for decoupled side effects', 'Queued jobs with ShouldQueue', 'Inertia::defer() for non-blocking props', 'sonner toast for user feedback', 'motion/react for animations', 'PHPUnit with RefreshDatabase + Factories + Event/Queue fakes']
test_patterns: ['PHPUnit Feature tests with RefreshDatabase', 'Event::fake() and Queue::fake() for isolation', 'TransactionCategorizer::fake() for AI mocking', 'Factory states (completed(), pending())', 'Inertia::assertComponent() for controller tests']
---

# Tech-Spec: Batch Categorization with Progress Tracking

**Created:** 2026-02-22

## Overview

### Problem Statement

Large CSV imports (200+ transactions) exceed the 90-second queue `retry_after` window because `CategorizeTransactions` processes all chunks sequentially in a single job — e.g., 4 chunks × 60s Gemini timeout = 240s potential runtime. This causes phantom re-queues and unreliable categorization. Additionally, users have zero visibility into categorization progress: the import is marked `completed` before categorization even starts, and no UI feedback exists to show whether AI processing is running, how far along it is, or when it finishes.

### Solution

Replace the monolithic `CategorizeTransactions` job with Laravel's `Bus::batch()` — each chunk of 50 transactions becomes its own independent job within a batch. Each job stays well under the 90s timeout. Track the import through its full lifecycle with new status states (`categorizing` → `categorized`). Expose a polling endpoint that returns batch progress. Show a toast notification after upload and an inline dashboard indicator for ongoing categorization. On chunk failure, leave transactions with `category_id = null` (no fallback category) so they can be re-categorized later. Provide a re-categorize action for imports with remaining uncategorized transactions.

### Scope

**In Scope:**
- Refactor `CategorizeTransactions` into per-chunk batch jobs using `Bus::batch()`
- Update Import model status lifecycle: `pending` → `completed` → `categorizing` → `categorized`
- Store batch ID on the Import model for progress queries
- Add polling API endpoint returning batch progress
- Toast notification after upload showing categorization has started
- Inline dashboard indicator showing categorization progress for active imports
- Fix queue/job timeout configuration for individual chunk jobs
- Re-categorize action for imports with uncategorized transactions
- Cancel batch on import deletion

**Out of Scope:**
- WebSocket/Reverb broadcasting infrastructure
- Changes to the Gemini AI agent or prompt engineering
- Fuzzy merchant matching improvements
- Changes to CSV parsing, deduplication, or import flow
- Multi-queue configuration

## Context for Development

### Codebase Patterns

- **Service layer:** Business logic in `app/Services/`. Constructor injection via `__construct(private ServiceName $service)`.
- **Event-driven:** `ImportCompleted` fires after CSV import. `CategorizeImportedTransactions` listener handles it synchronously. Wired in `AppServiceProvider::boot()`.
- **Job structure:** `ShouldQueue` + `Dispatchable` + `InteractsWithQueue` + `Queueable` + `SerializesModels`. Model instances in constructor.
- **Queue:** Database driver, `retry_after: 90s`. Single `default` queue. `job_batches` table exists but unused.
- **Frontend:** Inertia.js 2 with `Inertia::defer()`. `sonner` Toaster in `app-layout.tsx`. `motion/react` for animations. TypeScript strict mode.
- **Import status:** Currently `pending`, `completed`, `failed`. Set to `completed` in `CsvImportService::import()`.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `app/Jobs/CategorizeTransactions.php` | Current monolithic job — refactor into batch orchestrator |
| `app/Services/CategorizationService.php` | Orchestrator: merchant match → AI dispatch |
| `app/Listeners/CategorizeImportedTransactions.php` | Synchronous listener calling CategorizationService |
| `app/Models/Import.php` | Import model — needs `batch_id`, status lifecycle |
| `app/Http/Controllers/ImportController.php` | `store()`, `destroy()` — needs polling + batch cancel |
| `app/Http/Controllers/DashboardController.php` | `getImportRecords()` — needs status + uncategorized count |
| `app/Services/CsvImportService.php` | Sets import to `completed` — no changes |
| `config/categorization.php` | batch_size (50), confidence_threshold (0.5) |
| `resources/js/pages/dashboard.tsx` | Dashboard — needs progress indicator + toast |
| `resources/js/components/ImportHistory.tsx` | Import list — needs status + re-categorize |
| `resources/js/types/budget.ts` | TypeScript types — needs updates |
| `database/factories/ImportFactory.php` | Factory — needs new states |

### Technical Decisions

- **Polling over WebSockets:** Simpler architecture, no additional infrastructure. Poll every 2-3s during active categorization.
- **Bus::batch():** Native progress tracking, failure handling, completion callbacks. `job_batches` table already exists.
- **Import status lifecycle:** `pending` → `completed` (rows inserted) → `categorizing` (batch dispatched) → `categorized` (batch finished).
- **No fallback on failure:** Failed chunks leave transactions with `category_id = null`. Removes fallback-to-"other" behavior.
- **Merchant matching stays synchronous:** Fast in-memory lookup. If all matched, import goes directly to `categorized`.
- **Batch cancellation on delete:** `Bus::findBatch()->cancel()` when import deleted.
- **Re-categorize action:** Re-dispatches batch for `null` category transactions on a given import.
- **`allowFailures()`:** Successful chunks commit independently.
- **`CategorizeTransactionChunk` job:** Processes one chunk. Receives array of transaction IDs. `$timeout = 75`.
- **Refactored `CategorizeTransactions`:** No longer `ShouldQueue`. Called synchronously from service to create the batch.

## Implementation Plan

### Tasks

- [ ] **Task 1: Migration — add `batch_id` to imports**
  - File: `database/migrations/2026_02_22_000000_add_batch_id_to_imports_table.php` (new)
  - Action: Add nullable `string` column `batch_id` after `status`. No foreign key (batch IDs are UUIDs stored in `job_batches`).
  - Notes: Timestamp prefix follows existing convention (`2026_02_20_*`).

- [ ] **Task 2: Update Import model**
  - File: `app/Models/Import.php`
  - Action: Add `batch_id` to `$fillable`. Add helper methods: `isCategorizing(): bool` (status === 'categorizing'), `isCategorized(): bool` (status === 'categorized'), `hasUncategorizedTransactions(): bool` (query count of `category_id = null`).
  - Notes: No enum cast needed — status stays as plain string, consistent with existing pattern.

- [ ] **Task 3: Update ImportFactory**
  - File: `database/factories/ImportFactory.php`
  - Action: Add `categorizing()` and `categorized()` factory states. Update the `randomElement` in `definition()` to include new statuses.
  - Notes: `categorizing()` should set a fake batch_id UUID.

- [ ] **Task 4: Create `CategorizeTransactionChunk` job**
  - File: `app/Jobs/CategorizeTransactionChunk.php` (new)
  - Action: New job implementing `ShouldQueue` + `Batchable`. Constructor receives `array $transactionIds` (not model instances). `handle()` method:
    1. Fetch transactions by IDs where `category_id IS NULL`
    2. Build numbered prompt (same format as current job)
    3. Call `TransactionCategorizer->prompt()`
    4. In a DB transaction: update transactions with AI results. If confidence >= threshold AND valid slug → assign category. Otherwise leave `category_id = null` (NO fallback).
    5. Dispatch `TransactionCategorized` event for each categorized transaction.
    6. On catch: log error, throw to let batch track the failure.
  - Properties: `public int $tries = 3`, `public array $backoff = [30, 60, 120]`, `public int $timeout = 75`.
  - Notes: Uses `Batchable` trait for `Bus::batch()` awareness. Each job processes exactly one chunk. Transaction IDs are plain integers, not serialized models. Job is idempotent because of `whereNull('category_id')` filter.

- [ ] **Task 5: Refactor `CategorizeTransactions` into batch orchestrator**
  - File: `app/Jobs/CategorizeTransactions.php`
  - Action: Remove `ShouldQueue` and related traits. Convert to a plain class with a static method `dispatch(Import $import): void` that:
    1. Query uncategorized transactions for the import: `Transaction::where('import_id', $import->id)->whereNull('category_id')->pluck('id')`
    2. Chunk the IDs by `config('categorization.batch_size', 50)`
    3. Build array of `CategorizeTransactionChunk` jobs from chunks
    4. Dispatch `Bus::batch($jobs)->allowFailures()->then(fn () => ...)-> catch(fn () => ...)->finally(fn () => ...)->dispatch()`
    5. `then()`: Update import status to `categorized`
    6. `catch()`: Log the error (no status change — `finally` handles it)
    7. `finally()`: Update import status to `categorized` (batch is done regardless of partial failures)
    8. Store the batch ID on the import: `$import->update(['batch_id' => $batch->id, 'status' => 'categorizing'])`
  - Notes: The class name stays `CategorizeTransactions` for backwards compatibility in tests. It's no longer a queued job — it runs synchronously to set up the batch, then the individual chunk jobs run async.

- [ ] **Task 6: Update `CategorizationService`**
  - File: `app/Services/CategorizationService.php`
  - Action: After merchant matching:
    - If remaining uncategorized count > 0: call `CategorizeTransactions::dispatch($import)` (same as current, but now creates a batch)
    - If all matched (remaining = 0): set `$import->update(['status' => 'categorized'])` — skip batch entirely
  - Notes: The service interface doesn't change, only the internal dispatch behavior.

- [ ] **Task 7: Add polling endpoint and re-categorize endpoint**
  - File: `app/Http/Controllers/ImportController.php`
  - Action: Add two new methods:
    1. `status(Import $import): JsonResponse` — returns JSON with import status, and if `batch_id` exists: fetch batch via `Bus::findBatch()` and return `{ status, progress, processedJobs, totalJobs, failedJobs, finishedAt }`. If batch not found or no batch_id, return just import status.
    2. `recategorize(Import $import): RedirectResponse` — re-triggers categorization for imports with uncategorized transactions. Calls `CategorizeTransactions::dispatch($import)`.
  - Update `destroy()`: Before deleting, check if `$import->batch_id` exists. If so, attempt `Bus::findBatch($import->batch_id)?->cancel()`. Then delete as normal.
  - Notes: `status()` returns JSON (not Inertia) since it's called via `fetch()` from the frontend polling.

- [ ] **Task 8: Add routes**
  - File: `routes/web.php`
  - Action: Add three routes:
    - `GET /imports/{import}/status` → `ImportController::status` (name: `imports.status`)
    - `POST /imports/{import}/recategorize` → `ImportController::recategorize` (name: `imports.recategorize`)
  - Notes: Both require `auth` + `verified` middleware, consistent with existing routes.

- [ ] **Task 9: Update `DashboardController`**
  - File: `app/Http/Controllers/DashboardController.php`
  - Action: Update `getImportRecords()`:
    - Change filter from `where('status', 'completed')` to `whereIn('status', ['completed', 'categorizing', 'categorized'])`
    - Add `uncategorizedCount` to each record: `$import->transactions()->whereNull('category_id')->count()`
    - Add `status` field to the returned array
    - Add `batchId` field (nullable) for frontend to know which imports are trackable
  - Notes: The `uncategorizedCount` is computed per-import. For the deferred prop context this is acceptable since it's a lazy-loaded list.

- [ ] **Task 10: Update TypeScript types**
  - File: `resources/js/types/budget.ts`
  - Action: Update `ImportRecord` type:
    ```typescript
    export type ImportRecord = {
        id: number;
        fileName: string;
        bank: string;
        transactionCount: number;
        dateFrom: string | null;
        dateTo: string | null;
        importedAt: string | null;
        status: 'completed' | 'categorizing' | 'categorized';
        uncategorizedCount: number;
        batchId: string | null;
    };
    ```
    Add new type:
    ```typescript
    export type CategorizationStatus = {
        status: string;
        progress: number;
        processedJobs: number;
        totalJobs: number;
        failedJobs: number;
        finishedAt: string | null;
    };
    ```

- [ ] **Task 11: Create `CategorizationProgress` component**
  - File: `resources/js/components/CategorizationProgress.tsx` (new)
  - Action: Create a component that displays categorization progress for a single import. Shows:
    - Import bank name + transaction count
    - Progress bar (processedJobs / totalJobs percentage)
    - Status text: "Categorizing... X/Y chunks" or "Categorized" or "X transactions need review"
    - Re-categorize button (only when `status === 'categorized'` AND `uncategorizedCount > 0`)
  - Props: `import: ImportRecord`, `onRecategorize: (importId: number) => void`
  - Style: Match existing `ImportHistory` card style (bg-surface/80, backdrop-blur, rounded-xl, border).
  - Notes: This component is rendered inside `ImportHistory` for each import that is `categorizing` or has `uncategorizedCount > 0`.

- [ ] **Task 12: Update `ImportHistory` component**
  - File: `resources/js/components/ImportHistory.tsx`
  - Action:
    - For each import in the list, show status indicator:
      - `categorizing`: animated spinner + "Categorizing..." text
      - `categorized` with `uncategorizedCount > 0`: warning indicator + "X uncategorized" + re-categorize button
      - `categorized` with `uncategorizedCount === 0`: checkmark or no special indicator
    - Add re-categorize handler: `router.post(\`/imports/${id}/recategorize\`)` with toast feedback
  - Notes: Keep the existing delete functionality unchanged.

- [ ] **Task 13: Update `dashboard.tsx` — polling + toast**
  - File: `resources/js/pages/dashboard.tsx`
  - Action:
    1. After successful import (`handleConfirmImport` → `onSuccess`), show a toast: `toast('Categorizing transactions...')` using sonner.
    2. Add polling logic: if any import in the `imports` array has `status === 'categorizing'`, start a `setInterval` that calls `router.reload({ only: ['imports'] })` every 3 seconds. Clear interval when no imports are categorizing.
    3. When polling detects a previously-categorizing import has become `categorized`, show a toast: `toast.success('Categorization complete')` and trigger a full data reload (`router.reload()`) to refresh category totals.
  - Notes: Use `useEffect` + `useRef` for the polling interval. Inertia's `router.reload({ only: ['imports'] })` is lightweight — it only refreshes the deferred imports prop.

- [ ] **Task 14: Update `config/categorization.php`**
  - File: `config/categorization.php`
  - Action: Update the `fallback_category_slug` comment to reflect that it's now only used for low-confidence results, not failures. On failure, transactions stay null.

- [ ] **Task 15: Update tests — `CategorizeTransactionsTest`**
  - File: `tests/Feature/CategorizeTransactionsTest.php`
  - Action: Refactor tests for the new architecture:
    - Test that `CategorizeTransactions::dispatch()` creates a batch with correct number of chunk jobs
    - Test that chunk jobs are sized according to `categorization.batch_size`
    - Test that import status is set to `categorizing` with a batch_id
    - Remove tests that test the monolithic job's `handle()` directly
    - Add `CategorizeTransactionChunkTest.php` (new) for per-chunk job tests:
      - Test chunk processes transactions and assigns categories via AI
      - Test chunk leaves transactions null on failure (no fallback)
      - Test chunk is idempotent (already-categorized transactions skipped)
      - Test chunk dispatches TransactionCategorized events
      - Test timeout configuration is 75s

- [ ] **Task 16: Update tests — `CategorizationServiceTest`**
  - File: `tests/Feature/CategorizationServiceTest.php`
  - Action: Update assertions:
    - `test_unmatched_transactions_trigger_categorize_job_dispatch`: Change to assert batch is dispatched (use `Bus::fake()`)
    - `test_no_job_dispatched_when_all_matched_by_merchant_rules`: Assert import status set to `categorized` directly
    - Add: `test_all_matched_sets_import_status_to_categorized`

- [ ] **Task 17: Update tests — `ImportControllerTest`**
  - File: `tests/Feature/ImportControllerTest.php`
  - Action: Add tests:
    - `test_status_endpoint_returns_batch_progress`: Create import with batch_id, assert JSON response
    - `test_status_endpoint_returns_import_status_without_batch`: Import without batch_id
    - `test_destroy_cancels_active_batch`: Create import with batch_id, assert batch cancelled
    - `test_recategorize_dispatches_new_batch`: Import with uncategorized transactions

### Acceptance Criteria

- [ ] **AC 1:** Given a CSV import with 200 transactions, when the import completes, then a batch of 4 chunk jobs (50 each) is dispatched and import status is set to `categorizing`.
- [ ] **AC 2:** Given a batch is running, when a chunk job completes, then transactions in that chunk are categorized and the batch progress updates.
- [ ] **AC 3:** Given a chunk job fails after 3 retries, when the batch finishes, then failed transactions remain with `category_id = null` (no fallback assigned) and import status is `categorized`.
- [ ] **AC 4:** Given an import with `status = categorizing`, when the user visits the dashboard, then they see an inline progress indicator with the categorization progress.
- [ ] **AC 5:** Given a CSV was just imported, when the import succeeds, then a toast notification appears saying categorization has started.
- [ ] **AC 6:** Given imports are being categorized, when the dashboard polls every 3 seconds, then the progress indicator updates in real-time. When categorization completes, a success toast appears and dashboard data refreshes.
- [ ] **AC 7:** Given an import with `status = categorized` and uncategorized transactions remaining, when the user clicks "Re-categorize", then a new batch is dispatched for the remaining transactions.
- [ ] **AC 8:** Given an import is being categorized, when the user deletes that import, then the active batch is cancelled and the import is deleted.
- [ ] **AC 9:** Given all transactions in an import are matched by merchant rules, when categorization runs, then no batch is dispatched and the import status goes directly to `categorized`.
- [ ] **AC 10:** Given a single chunk job, when it runs, then it completes within the 75-second timeout (under the 90s `retry_after`).
- [ ] **AC 11:** Given the polling endpoint is called for an import, when the import has an active batch, then the response includes `progress`, `processedJobs`, `totalJobs`, `failedJobs`.

## Additional Context

### Dependencies

- **No new packages.** `Bus::batch()` is built into Laravel. `sonner` already installed. Polling uses `setInterval` + Inertia `router.reload()`.
- **Database:** One new migration for `batch_id` column. `job_batches` table already exists.
- **Queue worker:** Restart after deployment to pick up new job class.

### Testing Strategy

**Backend (PHPUnit):**
- `CategorizeTransactionChunkTest.php` (new): Test per-chunk AI categorization, no-fallback on failure, idempotency, event dispatch. Use `TransactionCategorizer::fake()`.
- `CategorizeTransactionsTest.php` (refactored): Test batch creation, chunk sizing, import status updates. Use `Bus::fake()`.
- `CategorizationServiceTest.php` (updated): Test batch dispatch for unmatched, direct `categorized` for all-matched. Use `Bus::fake()`.
- `ImportControllerTest.php` (updated): Test polling endpoint JSON response, batch cancel on delete, re-categorize endpoint.

**Frontend (Vitest):**
- `CategorizationProgress.test.tsx` (new): Test rendering of progress bar, status text, re-categorize button visibility.
- `ImportHistory.test.tsx` (if exists, update): Test status indicators, re-categorize handler.

**Manual Testing:**
1. Import a CSV with 100+ transactions → verify batch is created and progress shows
2. Watch progress bar update as chunks complete
3. Delete an import mid-categorization → verify batch cancels
4. Trigger a Gemini failure (e.g., invalid API key) → verify transactions stay null, re-categorize appears
5. Click re-categorize → verify new batch dispatched

### Notes

- `job_batches` table already migrated — no infrastructure setup needed.
- `CategorizeImportedTransactions` listener stays synchronous. Merchant matching is fast, batch dispatch is instant.
- Each chunk job is idempotent via `whereNull('category_id')`.
- `DashboardController::getImportRecords()` currently filters `where('status', 'completed')` — must include new statuses.
- `ImportRecord` TypeScript type gains `status`, `uncategorizedCount`, `batchId` fields.
- The `Batchable` trait on `CategorizeTransactionChunk` gives it batch awareness (`$this->batch()`, `$this->batch()->cancelled()`).
- Consider checking `$this->batch()->cancelled()` at the start of each chunk job to short-circuit if the import was deleted.
