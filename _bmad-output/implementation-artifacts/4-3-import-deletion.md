# Story 4.3: Import Deletion

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As Lambert,
I want to delete an import and all its linked transactions when I imported the wrong file or wrong account context,
so that I can recover from mistakes without manual cleanup.

## Acceptance Criteria

1. **Given** I want to manage my imports, **When** I access the import history (accessible from the Dashboard), **Then** I see a list of recent imports with bank source, transaction count, and date range.

2. **Given** I select an import to delete, **When** the deletion dialog appears (shadcn/ui Dialog), **Then** it shows: "Delete this import? [X] transactions from [Bank] ([Date range]) will be removed." with "Cancel" (secondary) and "Delete" (destructive, error-colored) actions (FR35).

3. **Given** I tap "Delete" in the confirmation dialog, **When** the deletion executes, **Then** the Import record and all linked transactions are removed (cascading deletion), category amounts update, and a toast confirms "Import deleted" (FR35).

4. **Given** I tap "Cancel" in the confirmation dialog, **When** the dialog closes, **Then** no data is deleted.

5. **Given** an import is deleted that contained transactions with merchant rules, **When** the deletion completes, **Then** the learned merchant rules are preserved -- they were learned from corrections, not from the import itself.

## Tasks / Subtasks

- [x] Task 1: Add `destroy` method to ImportController (AC: 3, 4, 5)
  - [x] 1.1: Add `destroy(Import $import): RedirectResponse` method to `app/Http/Controllers/ImportController.php`
  - [x] 1.2: Delete the Import record -- cascade delete on the `import_id` FK in the transactions migration handles removing all linked transactions automatically
  - [x] 1.3: Return `back()` redirect so Inertia refreshes the dashboard with updated data
  - [x] 1.4: No event dispatch needed -- this is a simple destructive operation, no downstream listeners required

- [x] Task 2: Add `imports.destroy` route (AC: 3)
  - [x] 2.1: Add `Route::delete('imports/{import}', [ImportController::class, 'destroy'])->name('imports.destroy')` to `routes/web.php`
  - [x] 2.2: Place inside the existing `auth` + `verified` middleware group alongside `imports.preview` and `imports.store`

- [x] Task 3: Add imports data to DashboardController (AC: 1)
  - [x] 3.1: In `DashboardController::index()`, query `Import::where('status', 'completed')->orderByDesc('imported_at')->get()` to get recent imports
  - [x] 3.2: Pass `imports` as an Inertia prop (camelCase) with each import containing: `id`, `fileName`, `bank`, `transactionCount`, `dateFrom`, `dateTo`, `importedAt`
  - [x] 3.3: Define a TypeScript `ImportRecord` type in `resources/js/types/budget.ts`

- [x] Task 4: Create ImportHistory component (AC: 1)
  - [x] 4.1: Create `resources/js/components/ImportHistory.tsx` displaying a list of imports
  - [x] 4.2: Each import row shows: bank source, transaction count, date range (formatted), and a delete button (Trash2 icon from lucide-react, destructive ghost variant)
  - [x] 4.3: Render inside a surface-colored card matching the existing design system (`bg-[#1C1C1E]`, `rounded-xl`)
  - [x] 4.4: Place the ImportHistory on the Dashboard below the category cards, behind a collapsible header "Import History" (secondary text, chevron toggle) -- not prominent but findable per UX spec

- [x] Task 5: Add delete confirmation dialog (AC: 2, 4)
  - [x] 5.1: Use the existing `Dialog` component from `resources/js/components/ui/dialog.tsx`
  - [x] 5.2: Dialog content: "Delete this import?" as title, "[X] transactions from [Bank] ([DateFrom] - [DateTo]) will be removed." as description
  - [x] 5.3: Footer actions: "Cancel" button (secondary/outline variant) and "Delete" button (`variant="destructive"`)
  - [x] 5.4: Track `deletingImportId` state -- when set, dialog opens for that import; on cancel/close, reset to null

- [x] Task 6: Wire delete action with Inertia (AC: 3, 4)
  - [x] 6.1: On "Delete" confirmation, call `router.delete(`/imports/${importId}`, { preserveScroll: true, onSuccess: () => { toast("Import deleted"); setDeletingImportId(null); } })`
  - [x] 6.2: Add loading state (`isDeleting`) to disable the delete button during the request and prevent double-clicks
  - [x] 6.3: On success: toast fires, dialog closes, Inertia refreshes page props (category amounts, available months auto-update)
  - [x] 6.4: On cancel: close dialog, no request sent

- [x] Task 7: Write Feature Tests (AC: 1-5)
  - [x] 7.1: Test `DELETE /imports/{import}` deletes the Import record
  - [x] 7.2: Test cascade: all linked transactions are deleted when Import is deleted
  - [x] 7.3: Test merchant rules are preserved after import deletion (FR35 AC5)
  - [x] 7.4: Test unauthenticated user cannot delete imports (redirects to login)
  - [x] 7.5: Test dashboard includes imports list in Inertia props
  - [x] 7.6: Test deleting an import that results in an empty month -- available months should update
  - [x] 7.7: Test deleting a non-existent import returns 404
  - [x] 7.8: Run `sail artisan test` -- all existing + new tests pass with zero regressions
  - [x] 7.9: Verify TypeScript compiles cleanly: `sail bun run build`

## Dev Notes

### Architecture Overview for This Story

This story adds the import deletion safety net to the budget-app. It's the last story in Epic 4 (Category Correction & AI Learning). While the other stories in this epic focus on the correction and learning flow, this story provides a recovery mechanism for when the wrong file was imported entirely (wrong bank, wrong account context, or duplicate file that somehow got through).

The implementation is straightforward because the database schema already supports cascading deletion -- the `transactions` migration defines `foreignId('import_id')->nullable()->constrained()->cascadeOnDelete()`. Deleting an Import record automatically removes all its linked transactions at the database level, which is both atomic and performant.

The UI follows the existing Dashboard patterns: an expandable section for import history, a confirmation Dialog (same component used for import preview), and a sonner toast for feedback.

```
Dashboard.tsx (MODIFIED -- add ImportHistory section + delete dialog)
  │
  ├─ ImportHistory component (NEW)
  │     │
  │     └─ Import rows with delete button (Trash2 icon)
  │           │
  │           └─ Opens confirmation Dialog
  │                 │
  │                 ├─ "Cancel" → close dialog
  │                 └─ "Delete" → router.delete('/imports/{id}')
  │                       │
  │                       └─ ImportController::destroy() (NEW method)
  │                             │
  │                             └─ Import::delete() → CASCADE removes transactions
  │                                   │
  │                                   └─ Inertia refreshes Dashboard props
  │                                         │
  │                                         ├─ Category amounts update
  │                                         ├─ Available months update
  │                                         └─ Import list updates
  └─ toast("Import deleted") on success
```

**Data flow for import deletion:**

```
User taps delete icon on import row in ImportHistory
  → Confirmation Dialog opens with import details
  → User taps "Delete"
  → router.delete('/imports/{id}', { preserveScroll: true })
  → ImportController::destroy(Import $import)
    → $import->delete()
    → PostgreSQL CASCADE: DELETE FROM transactions WHERE import_id = ?
    → merchant_rules table UNAFFECTED (no FK to imports or transactions)
  → return back()
  → Inertia refreshes Dashboard page props:
    → DashboardController::index() re-queries via BudgetQueryService
    → Category totals recalculated (deleted transactions excluded)
    → Available months recalculated (empty months may disappear)
    → Import list updated (deleted import removed)
  → onSuccess callback:
    → toast("Import deleted") appears (auto-dismiss 2s)
    → Dialog closes
    → UI reflects updated amounts
```

**Key design decisions:**

- **CASCADE delete, not manual deletion:** The `cascadeOnDelete()` on the `import_id` foreign key means `$import->delete()` is all that's needed. No manual `Transaction::where('import_id', $id)->delete()` -- the database handles it atomically. This is the most reliable approach and was already decided during the database schema design (Story 1.2).

- **No event dispatch on deletion:** Unlike corrections (which fire `CategoryCorrected`), import deletion doesn't need an event. There are no downstream listeners that need to react to a deleted import. The UI updates happen via Inertia's automatic page refresh on redirect.

- **Merchant rules preserved by design:** The `merchant_rules` table has no foreign key to `imports` or `transactions`. Rules are created by the `UpdateMerchantRule` listener on `CategoryCorrected` events. Deleting an import doesn't touch the rules -- they were learned from user corrections, not from the import itself. This satisfies AC5 without any special handling.

- **Collapsible import history, not a separate page:** Per UX spec, import deletion should be "accessible but not prominent." A collapsible section at the bottom of the Dashboard keeps the import history findable without cluttering the primary experience (category overview). No additional route or page needed.

- **No pagination on import history:** The budget-app is a single-user personal finance tool. The number of imports is modest (bi-weekly imports from 2 banks). A simple list of all completed imports is sufficient. No pagination, no search, no filtering.

- **`preserveScroll: true` on delete:** After deletion, the user stays at their current scroll position on the Dashboard. The import history section updates (the deleted import disappears), and category amounts above may change, but the user doesn't lose their place.

- **Loading state on delete button:** A `isDeleting` state guard prevents double-clicks on the destructive delete button. Same pattern as `isPatching` in Story 4.1 and `isUploading` in the import flow.

### ImportController destroy Method

```php
// app/Http/Controllers/ImportController.php -- add destroy method
public function destroy(Import $import): RedirectResponse
{
    $import->delete();

    return back();
}
```

**Why this simple:** The cascade delete handles transaction removal. Inertia's redirect back triggers `DashboardController::index()` which re-queries all data via `BudgetQueryService`. Category amounts, available months, and the import list all update automatically. No manual cleanup, no event dispatch, no side effects to manage.

### Route Addition

```php
// routes/web.php -- add to the auth+verified middleware group
Route::delete('imports/{import}', [ImportController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('imports.destroy');
```

### DashboardController Update

```php
// app/Http/Controllers/DashboardController.php -- add imports to index()
use App\Models\Import;

// Inside index() method, add:
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

// Add to Inertia::render props:
'imports' => $imports,
```

**Key details:**
- Only show completed imports (not pending or failed)
- Ordered by most recent first
- Props in camelCase per Inertia convention
- Dates as ISO strings for frontend formatting
- `transactionCount` as integer (already cast in model)

### TypeScript Types

```typescript
// resources/js/lib/types.ts -- add ImportRecord type
export interface ImportRecord {
    id: number;
    fileName: string;
    bank: string;
    transactionCount: number;
    dateFrom: string | null;
    dateTo: string | null;
    importedAt: string | null;
}
```

### ImportHistory Component

```tsx
// resources/js/components/ImportHistory.tsx
import { useState } from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Trash2, ChevronDown, ChevronUp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription,
    DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import type { ImportRecord } from '@/lib/types';

// Component accepts imports array and renders:
// - Collapsible header "Import History" with chevron toggle
// - List of import rows: bank, transaction count, date range, delete icon
// - Confirmation dialog when delete is clicked
// - router.delete on confirm with toast on success
```

**Styling:**
- Collapsible header: `text-[#A1A1AA]` secondary text, `text-sm font-medium`, clickable with ChevronDown/Up toggle
- Import rows: `py-3` padding, `border-b border-[#2C2C2E]` separator (last item no border)
- Bank name: `text-sm text-white font-medium`
- Transaction count and date range: `text-xs text-[#A1A1AA]`
- Delete button: `variant="ghost"` `size="icon"` with `Trash2` icon in `text-[#71717A]` (tertiary), hover to `text-[#EF4444]` (error color)
- Card container: `bg-[#1C1C1E] rounded-xl p-4 mt-6`

### Dialog Content

```tsx
<Dialog open={deletingImportId !== null} onOpenChange={(open) => !open && setDeletingImportId(null)}>
    <DialogContent>
        <DialogHeader>
            <DialogTitle>Delete this import?</DialogTitle>
            <DialogDescription>
                {selectedImport?.transactionCount} transactions from {selectedImport?.bank}
                ({formatDate(selectedImport?.dateFrom)} - {formatDate(selectedImport?.dateTo)})
                will be removed.
            </DialogDescription>
        </DialogHeader>
        <DialogFooter>
            <Button variant="outline" onClick={() => setDeletingImportId(null)}>
                Cancel
            </Button>
            <Button variant="destructive" onClick={handleDelete} disabled={isDeleting}>
                Delete
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
```

### Dashboard Integration

The ImportHistory component is placed below the category cards list on the Dashboard, inside the existing content container. It only renders when `imports.length > 0` -- if there are no imports, the section doesn't appear (the EmptyState handles the no-data case).

```tsx
// In Dashboard.tsx, after the category cards map:
{imports.length > 0 && (
    <ImportHistory imports={imports} />
)}
```

### Testing Strategy

**Feature tests in `tests/Feature/ImportControllerTest.php` (EXTEND existing):**

```php
// Add these tests to the existing ImportControllerTest

public function test_authenticated_user_can_delete_import(): void
{
    $user = User::factory()->create();
    $account = Account::factory()->personal()->create();
    $import = Import::factory()->create(['status' => 'completed']);
    Transaction::factory()->count(5)->create([
        'import_id' => $import->id,
        'account_id' => $account->id,
    ]);

    $this->actingAs($user)
        ->delete("/imports/{$import->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('imports', ['id' => $import->id]);
    $this->assertDatabaseCount('transactions', 0);
}

public function test_cascade_deletes_all_linked_transactions(): void
{
    $user = User::factory()->create();
    $account = Account::factory()->personal()->create();
    $import = Import::factory()->create(['status' => 'completed']);
    $otherImport = Import::factory()->create(['status' => 'completed']);

    Transaction::factory()->count(3)->create([
        'import_id' => $import->id,
        'account_id' => $account->id,
    ]);
    Transaction::factory()->count(2)->create([
        'import_id' => $otherImport->id,
        'account_id' => $account->id,
    ]);

    $this->actingAs($user)
        ->delete("/imports/{$import->id}");

    $this->assertDatabaseCount('transactions', 2); // Only other import's transactions remain
    $this->assertDatabaseHas('imports', ['id' => $otherImport->id]);
}

public function test_merchant_rules_preserved_after_import_deletion(): void
{
    $user = User::factory()->create();
    $account = Account::factory()->personal()->create();
    $food = Category::where('slug', 'food')->first();

    MerchantRule::create([
        'merchant_pattern' => 'CARREFOUR CITY',
        'category_id' => $food->id,
    ]);

    $import = Import::factory()->create(['status' => 'completed']);
    Transaction::factory()->create([
        'import_id' => $import->id,
        'account_id' => $account->id,
        'description' => 'CARREFOUR CITY',
        'category_id' => $food->id,
    ]);

    $this->actingAs($user)
        ->delete("/imports/{$import->id}");

    // Merchant rule survives import deletion
    $this->assertDatabaseHas('merchant_rules', [
        'merchant_pattern' => 'CARREFOUR CITY',
        'category_id' => $food->id,
    ]);
}

public function test_unauthenticated_user_cannot_delete_import(): void
{
    $import = Import::factory()->create(['status' => 'completed']);

    $this->delete("/imports/{$import->id}")
        ->assertRedirect('/login');

    $this->assertDatabaseHas('imports', ['id' => $import->id]);
}

public function test_dashboard_includes_imports_in_props(): void
{
    $user = User::factory()->create();
    $import = Import::factory()->create([
        'status' => 'completed',
        'bank' => 'Banque Populaire',
        'transaction_count' => 42,
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('imports', 1)
            ->where('imports.0.bank', 'Banque Populaire')
            ->where('imports.0.transactionCount', 42)
        );
}

public function test_deleting_import_updates_available_months(): void
{
    $user = User::factory()->create();
    $account = Account::factory()->personal()->create();
    $food = Category::where('slug', 'food')->first();

    $import = Import::factory()->create(['status' => 'completed']);
    Transaction::factory()->create([
        'import_id' => $import->id,
        'account_id' => $account->id,
        'category_id' => $food->id,
        'date' => '2026-01-15',
    ]);

    // January should be in available months
    $this->actingAs($user)
        ->get('/?month=2026-01')
        ->assertInertia(fn (Assert $page) => $page
            ->where('availableMonths', fn ($months) => in_array('2026-01', $months))
        );

    // Delete import
    $this->actingAs($user)
        ->delete("/imports/{$import->id}");

    // January should no longer be available (no transactions left)
    $this->actingAs($user)
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('availableMonths', fn ($months) => !in_array('2026-01', $months))
        );
}

public function test_deleting_nonexistent_import_returns_404(): void
{
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete('/imports/99999')
        ->assertNotFound();
}
```

### Previous Story Intelligence

**From Story 4.2 (AI Learning from Corrections -- direct predecessor):**
- 241 tests, 1066 assertions, zero regressions -- maintain this standard
- All commands via Sail: `sail artisan test`, `sail bun run build`
- Sonner toast is already installed and configured in `app-layout.tsx`
- Event-driven architecture established -- but this story does NOT need events (simple delete)
- `CategoryCorrected` → `UpdateMerchantRule` pipeline is in place -- rules survive deletion by design (no FK relationship)

**From Story 4.1 (Single-Tap Category Correction):**
- Dialog component is already installed and used for import confirmation
- `Button` component has `variant="destructive"` available
- `isPatching` loading state pattern used to prevent concurrent requests -- use same pattern (`isDeleting`) for delete button

**From Story 2.6 (Import UI & Confirmation):**
- `ImportController` has `preview()` and `store()` methods -- add `destroy()` alongside
- Import confirmation Dialog is already in Dashboard.tsx -- similar pattern for delete confirmation
- `ImportPreview` type exists in `types.ts` -- add `ImportRecord` type alongside

**From Story 2.3 (Import Pipeline with Deduplication):**
- `Import` model has `transactions()` HasMany relationship
- `cascadeOnDelete()` is set on the `import_id` FK in transactions migration
- `CsvImportService` creates imports with status lifecycle: pending → completed/failed
- `Import` casts: `imported_at` → datetime, `date_from`/`date_to` → date, `transaction_count` → integer

### Git Intelligence

Recent commits (one per story, mono-commit pattern):
- `4c016d9` Story 4.2: AI learning from corrections with event-driven merchant rules + review fixes
- `dde98cc` Story 4.1: Single-tap category correction with responsive CategoryPicker + review fixes

**Established patterns:**
- One commit per story with descriptive message
- All commands via Sail (`sail artisan test`, `sail bun run build`)
- Tests must pass with zero regressions before committing
- Feature tests use `RefreshDatabase` trait + seed categories
- Test file naming: `{Subject}Test.php` in `tests/Feature/`

### Architecture Compliance

| Requirement | Implementation |
|-------------|----------------|
| Import deletion with cascade [epics#Story 4.3] | `$import->delete()` triggers `cascadeOnDelete()` on FK |
| Dialog for destructive confirmation [ux#Confirmation Feedback] | shadcn/ui Dialog with "Cancel" (outline) + "Delete" (destructive) |
| Toast for success feedback [ux#Feedback Patterns] | Sonner toast: "Import deleted" -- 2s auto-dismiss |
| Merchant rules preserved [epics#AC5] | No FK between merchant_rules and imports/transactions -- rules unaffected |
| Import history accessible but not prominent [ux#Import Deletion] | Collapsible section below category cards |
| Inertia props in camelCase [arch#Naming Patterns] | `transactionCount`, `dateFrom`, `dateTo`, `importedAt` |
| `router.delete()` for destructive actions [arch#Communication Patterns] | Inertia's `router.delete('/imports/{id}')` -- never raw fetch |
| Button variant="destructive" [ux#Component Strategy] | Error-colored delete button in confirmation dialog |

### Library & Framework Requirements

No new packages required for this story. All dependencies are already installed:
- `sonner` (toast) -- installed in Story 4.2
- `@radix-ui/react-dialog` (Dialog) -- installed with shadcn/ui in Story 1.3
- `lucide-react` (Trash2 icon) -- installed with starter kit

### File Structure Requirements

**New files to create:**
```
resources/js/components/ImportHistory.tsx              (NEW)
```

**Modified files:**
```
app/Http/Controllers/ImportController.php              (add destroy method)
app/Http/Controllers/DashboardController.php           (add imports to props)
routes/web.php                                         (add imports.destroy route)
resources/js/pages/dashboard.tsx                       (add ImportHistory section + import types to props)
resources/js/lib/types.ts                              (add ImportRecord type)
tests/Feature/ImportControllerTest.php                 (add deletion tests)
```

### What This Story Does NOT Include

- **Import editing** -- No ability to change which account an import belongs to. If the wrong account context was used, delete and re-import.
- **Partial import deletion** -- No ability to delete individual transactions from an import. The entire import is deleted as a unit.
- **Import undo/restore** -- Deletion is permanent. No soft delete, no trash, no undo beyond re-importing the CSV.
- **Bulk import deletion** -- Each import is deleted individually. No "select all" or batch deletion.
- **Import status management** -- Only completed imports are shown. Pending or failed imports are not visible in the history.
- **Merchant rule cleanup** -- Rules are preserved intentionally. No option to "also delete rules learned from this import's transactions."

### Edge Cases to Handle

1. **Last import for a month:** If deleting an import removes all transactions for a given month, that month should disappear from `availableMonths`. The BudgetQueryService already handles this -- it queries months with actual transaction data.

2. **Delete while viewing that month:** If the user is viewing January and deletes the only import that had January data, the Dashboard will re-render with updated props. The month strip may lose January, and the current month may fall back to the most recent month with data.

3. **Import with zero transactions:** An import could have `transaction_count: 0` if all transactions were deduplicated during import. Deleting it still works (no transactions to cascade) and removes it from the list.

4. **Concurrent delete clicks:** The `isDeleting` loading state disables the delete button after the first click. The `onFinish` callback re-enables it. Same pattern as `isPatching` in Story 4.1.

5. **Network failure during delete:** If the DELETE request fails, Inertia will show an error. The import and its transactions remain intact -- the operation hasn't committed. The user can retry.

6. **Dialog dismissed by clicking overlay:** The `onOpenChange` handler resets `deletingImportId` to null when the dialog is closed by any means (overlay click, escape key, cancel button). No delete request is sent.

### Project Structure Notes

- ImportController extended in `app/Http/Controllers/` (following existing pattern) [Source: architecture.md#Structure Patterns]
- ImportHistory component in `resources/js/components/` (following CategoryCard, MonthStrip pattern) [Source: architecture.md#Structure Patterns]
- Types in `resources/js/lib/types.ts` (following existing ImportPreview type) [Source: architecture.md#Structure Patterns]
- Tests in `tests/Feature/ImportControllerTest.php` (extending existing file) [Source: architecture.md#Test Organization]

### References

- Epic 4 story requirements: [Source: _bmad-output/planning-artifacts/epics.md#Story 4.3]
- FR35 requirement: [Source: _bmad-output/planning-artifacts/epics.md#Requirements Inventory]
- Import deletion UX spec: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Action Patterns]
- Confirmation dialog spec: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Feedback Patterns]
- Dialog component: [Source: resources/js/components/ui/dialog.tsx]
- Import model with relationships: [Source: app/Models/Import.php]
- Transaction cascade delete migration: [Source: database/migrations/*_create_transactions_table.php]
- ImportController existing methods: [Source: app/Http/Controllers/ImportController.php]
- DashboardController props: [Source: app/Http/Controllers/DashboardController.php]
- Dashboard page structure: [Source: resources/js/pages/dashboard.tsx]
- Button destructive variant: [Source: resources/js/components/ui/button.tsx]
- Toast setup (sonner): [Source: resources/js/layouts/app/app-layout.tsx]
- Existing import tests: [Source: tests/Feature/ImportControllerTest.php]
- Previous story 4.2: [Source: _bmad-output/implementation-artifacts/4-2-ai-learning-from-corrections.md]
- Merchant rules table: [Source: app/Models/MerchantRule.php]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6

### Debug Log References

- One test failure during initial run: `in_array()` received Collection instead of array for `availableMonths` assertion. Fixed by using `->has('availableMonths', 0)` pattern matching existing DashboardTest conventions.

### Completion Notes List

- Task 1: Added `destroy(Import $import)` method to ImportController — single-line `$import->delete()` leveraging CASCADE FK
- Task 2: Added `Route::delete('imports/{import}')` with auth+verified middleware in routes/web.php
- Task 3: Added imports query to DashboardController, mapping to camelCase Inertia props; added `ImportRecord` type to `resources/js/types/budget.ts` and updated `DashboardProps`
- Task 4: Created `ImportHistory.tsx` component with collapsible header (ChevronDown/Up toggle), import rows with bank/count/date range, and Trash2 delete icon
- Task 5: Integrated shadcn/ui Dialog for delete confirmation with "Delete this import?" title, transaction details description, Cancel (outline) and Delete (destructive) buttons
- Task 6: Wired `router.delete()` with `preserveScroll: true`, `isDeleting` loading state, sonner toast on success, dialog close on cancel/overlay click
- Task 7: Added 7 feature tests (22 total in ImportControllerTest) — cascade deletion, merchant rules preservation, auth guard, dashboard imports prop, available months update, 404 for nonexistent. Full suite: 248 tests, 1113 assertions, zero regressions. TypeScript compiles cleanly.

### File List

- `app/Http/Controllers/ImportController.php` (MODIFIED — added destroy method + Import model import)
- `app/Http/Controllers/DashboardController.php` (MODIFIED — added imports query + Import model import)
- `routes/web.php` (MODIFIED — added imports.destroy DELETE route)
- `resources/js/types/budget.ts` (MODIFIED — added ImportRecord type, updated DashboardProps)
- `resources/js/components/ImportHistory.tsx` (NEW — collapsible import history with delete dialog)
- `resources/js/pages/dashboard.tsx` (MODIFIED — integrated ImportHistory component + imports prop)
- `tests/Feature/ImportControllerTest.php` (MODIFIED — added 7 deletion tests)

### Change Log

- 2026-02-21: Story 4.3 implementation — Import deletion with cascading transaction removal, collapsible import history UI with confirmation dialog, 7 new feature tests (248 total, zero regressions)
- 2026-02-21: Code review fixes — (1) Moved ImportHistory outside conditional branch so it's always accessible on Dashboard regardless of current month data state (HIGH fix), (2) Added onError callback with toast.error for failed delete requests (MEDIUM fix), (3) Added test_dashboard_excludes_pending_imports_from_props test (MEDIUM fix), (4) Aligned ImportHistory date range formatting with dashboard confirmation dialog format including year (MEDIUM fix). Suite: 249 tests, 1126 assertions, zero regressions.
