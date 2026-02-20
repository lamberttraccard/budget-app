# Story 2.5: Financial Overview Dashboard

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As Lambert,
I want to see a category overview with total amounts and an income/expenses summary for the current month,
so that I know where my money is going at a glance.

## Acceptance Criteria

1. **Given** I am logged in and transactions exist for the current month, **When** I open the application at `/dashboard`, **Then** I see the Dashboard page showing category cards with total amounts, sorted by absolute amount descending (FR12, FR17).

2. **Given** the Dashboard is displayed, **When** I look at the category cards, **Then** each card shows: a 48px rounded square filled with the category's accent color, the category name (14px regular), the transaction count (secondary text), and the total amount in the category's accent color (24px semibold, tabular-nums) (FR12).

3. **Given** the Dashboard is displayed, **When** I look at the summary widget at the top, **Then** I see two side-by-side cards: Income (emerald `#10B981`) and Expenses (rose/coral) showing their total absolute amounts for the current month (FR13).

4. **Given** amounts are displayed anywhere on the Dashboard, **When** I read any financial figure, **Then** it is formatted in French locale with tabular-nums: e.g. `"1 234,56 €"` (integer cents converted by `formatAmount.ts` using `Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' })`).

5. **Given** `BudgetQueryService` is implemented, **When** `DashboardController` requests monthly category totals, **Then** the service returns aggregated data via Eloquent (category totals with name, slug, color, amountCents, transactionCount) that the controller passes as camelCase Inertia props (NFR1).

6. **Given** no transactions exist for the current month, **When** the Dashboard renders, **Then** the category list area shows a simple empty message ("No transactions for [Month]. Import your first CSV to get started.") and the SummaryWidget shows `"0,00 €"` for both Income and Expenses.

## Tasks / Subtasks

- [x] Task 1: Create BudgetQueryService (AC: 5)
  - [x]1.1: Create `app/Services/BudgetQueryService.php` with constructor injection-ready structure
  - [x]1.2: Implement `getMonthlyData(int $year, int $month, ?string $accountType = null): array` — returns `['categoryTotals' => [...], 'incomeCents' => int, 'expensesCents' => int]`
  - [x]1.3: Category totals query: JOIN `transactions` with `categories` WHERE `YEAR(t.date) = $year AND MONTH(t.date) = $month`, GROUP BY `categories.id`, SELECT name/slug/color/SUM(amount_cents)/COUNT(id), ORDER BY ABS(SUM(amount_cents)) DESC. Use `whereYear()/whereMonth()` on Eloquent. Exclude categories with zero transactions.
  - [x]1.4: Income: sum of all transactions in the 'income' category for the month (positive amounts)
  - [x]1.5: Expenses: absolute value of sum of all non-'income' category transactions for the month (negative amounts). Use `ABS(SUM(...))` or PHP `abs()`.
  - [x]1.6: Return `categoryTotals` as array of dicts: `['id', 'name', 'slug', 'color', 'amountCents', 'transactionCount']`. Map Eloquent aggregates to this shape before returning.

- [x] Task 2: Create DashboardController (AC: 5)
  - [x]2.1: Create `app/Http/Controllers/DashboardController.php`
  - [x]2.2: Inject `BudgetQueryService` via constructor
  - [x]2.3: Implement `index(Request $request): Response` — parse `?month=YYYY-MM` param (default: `now()->format('Y-m')`). Validate format with regex. On invalid format, fall back to current month.
  - [x]2.4: Parse month param into `[$year, $month]` using `explode('-', $monthParam)`
  - [x]2.5: Call `$this->budgetQueryService->getMonthlyData($year, $month)` and merge with `currentMonth` into Inertia props
  - [x]2.6: Return `Inertia::render('dashboard', ['categoryTotals' => ..., 'incomeCents' => ..., 'expensesCents' => ..., 'currentMonth' => $monthParam])`
  - [x]2.7: Add `use Inertia\Inertia;` and `use Illuminate\Http\Request;` imports

- [x] Task 3: Update routes/web.php (AC: 1)
  - [x]3.1: Replace the `/dashboard` closure route with: `Route::get('dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');`
  - [x]3.2: Add `use App\Http\Controllers\DashboardController;` import at top of routes/web.php
  - [x]3.3: Remove `use Inertia\Inertia;` from web.php if no longer needed (the Inertia import was only for the dashboard closure). Keep if still used by the `/` welcome route — check: the welcome route also uses `Inertia::render()`, so keep the import.

- [x] Task 4: Create formatAmount.ts utility (AC: 4)
  - [x]4.1: Create `resources/js/lib/formatAmount.ts`
  - [x]4.2: Export `formatAmount(cents: number): string` — implementation: `return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(cents / 100);`
  - [x]4.3: This produces French-locale format: `"1 234,56 €"` with narrow-no-break-space as thousands separator, comma as decimal separator, and `€` suffix. Handles negative amounts correctly (expenses stored as negative cents display as `"-50,42 €"`).
  - [x]4.4: Export `formatAbsAmount(cents: number): string` — wraps `formatAmount(Math.abs(cents))` for displaying expense totals as positive values in the SummaryWidget

- [x] Task 5: Add TypeScript types (AC: 1, 2, 3)
  - [x]5.1: Create `resources/js/types/budget.ts` with domain types:
    ```typescript
    export type CategoryTotal = {
        id: number;
        name: string;
        slug: string;
        color: string; // hex, e.g. "#A3E635"
        amountCents: number; // negative for expenses
        transactionCount: number;
    };
    export type DashboardProps = {
        categoryTotals: CategoryTotal[];
        incomeCents: number;
        expensesCents: number; // absolute value (positive)
        currentMonth: string; // "YYYY-MM"
    };
    ```
  - [x]5.2: Add `export type * from './budget';` to `resources/js/types/index.ts`

- [x] Task 6: Create SummaryWidget component (AC: 3, 4)
  - [x]6.1: Create `resources/js/components/SummaryWidget.tsx`
  - [x]6.2: Props: `{ incomeCents: number; expensesCents: number; }`
  - [x]6.3: Render two side-by-side cards (flex row, gap-3):
    - Income card: label "Income" + emerald amount (`text-[#10B981]`) using `formatAmount(incomeCents)`
    - Expenses card: label "Expenses" + coral/rose amount (`text-[#FB7185]`) using `formatAmount(expensesCents)`
  - [x]6.4: Each card: `bg-surface rounded-xl p-4 flex-1`. Label: `text-xs text-text-secondary`. Amount: `text-lg font-semibold tabular-nums` in the respective color.

- [x] Task 7: Create CategoryCard component (AC: 2, 4)
  - [x]7.1: Create `resources/js/components/CategoryCard.tsx`
  - [x]7.2: Props: `{ name: string; color: string; amountCents: number; transactionCount: number; }`
  - [x]7.3: Layout: `flex items-center gap-4 bg-surface rounded-xl p-4`
  - [x]7.4: Color icon: `w-12 h-12 rounded-xl flex-shrink-0` with `style={{ backgroundColor: color }}` — solid colored square (48px, 12px radius). No icon image needed (icon data is null in DB; color IS the visual identity).
  - [x]7.5: Text column (flex-1): category name (`text-sm font-medium text-white`) above, transaction count (`text-xs text-text-secondary`, e.g., "12 transactions") below
  - [x]7.6: Amount (ml-auto): `text-lg font-semibold tabular-nums` with `style={{ color: color }}`. Display `formatAbsAmount(amountCents)` — always positive display value. Negative amountCents = expense category = show the absolute value.
  - [x]7.7: The entire card is NOT clickable in this story (clicking for drill-down is Story 3.1). No `onClick` handler, no cursor-pointer.

- [x] Task 8: Implement Dashboard.tsx (AC: 1, 2, 3, 4, 6)
  - [x]8.1: Replace the placeholder `resources/js/pages/dashboard.tsx` with full implementation
  - [x]8.2: Import: `{ Head } from '@inertiajs/react'`, `AppLayout`, `SummaryWidget`, `CategoryCard`, `{ CategoryTotal, DashboardProps }` types
  - [x]8.3: Component signature: `export default function Dashboard({ categoryTotals, incomeCents, expensesCents, currentMonth }: DashboardProps)`
  - [x]8.4: Render order: AppLayout > `<Head title="Budget" />` > `<div className="py-6 space-y-5">` > SummaryWidget > category list
  - [x]8.5: Empty state (when `categoryTotals.length === 0`): render a `<div className="rounded-xl bg-surface p-6 text-center">` with `<p className="text-sm text-text-secondary">No transactions for [formatted month]. Import your first CSV to get started.</p>`. Format currentMonth (e.g. "2026-02") as "February 2026" for display.
  - [x]8.6: Category list: `<div className="space-y-3">` containing `{categoryTotals.map(cat => <CategoryCard key={cat.id} {...cat} />)}`
  - [x]8.7: Format `currentMonth` for the page title: parse "2026-02" → "February 2026" using `new Date(${currentMonth}-01).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })` or English equivalent. Use English locale to match app language.

- [x] Task 9: Write Feature Tests for DashboardController (AC: 1–6)
  - [x]9.1: Update `tests/Feature/DashboardTest.php` — keep existing 2 tests (they still pass with DashboardController), add new tests below
  - [x]9.2: Test dashboard returns Inertia component 'dashboard' with correct prop shape: `$response->assertInertia(fn ($page) => $page->component('dashboard')->has('categoryTotals')->has('incomeCents')->has('expensesCents')->has('currentMonth'))`
  - [x]9.3: Test category totals are sorted by absolute amount descending — create transactions in different categories with varying amounts, assert the order in props
  - [x]9.4: Test incomeCents is sum of 'income' category transactions for the month
  - [x]9.5: Test expensesCents is absolute value of all non-income category transactions
  - [x]9.6: Test only returns transactions for the requested month (not other months)
  - [x]9.7: Test `?month=2026-01` param returns data for January 2026
  - [x]9.8: Test invalid `?month=invalid` param falls back to current month (returns 200 OK)
  - [x]9.9: Test empty state when no transactions for month: `categoryTotals` is empty array, `incomeCents` and `expensesCents` are 0
  - [x]9.10: Use `RefreshDatabase`, `Inertia::fake()` (via `use Inertia\Testing\AssertableInertia`), seed categories with `CategorySeeder`
  - [x]9.11: Use `Account::factory()->create(['type' => 'personal'])` for test accounts — import test transactions via factory, not CSV (no need to run the full import pipeline)

- [x] Task 10: Write Unit Tests for BudgetQueryService (AC: 5)
  - [x]10.1: Create `tests/Unit/BudgetQueryServiceTest.php` with `RefreshDatabase` trait (needs DB because Eloquent queries)
  - [x]10.2: Test `getMonthlyData()` returns correct structure: keys `categoryTotals`, `incomeCents`, `expensesCents`
  - [x]10.3: Test category totals include name, slug, color, amountCents, transactionCount per category
  - [x]10.4: Test categories with no transactions in the month are excluded from `categoryTotals`
  - [x]10.5: Test `incomeCents` correctly sums 'income' category transactions
  - [x]10.6: Test `expensesCents` is the absolute value of non-income category sums
  - [x]10.7: Test sorting: category with highest absolute amount appears first
  - [x]10.8: Test month filtering: only transactions from the specified year/month are included
  - [x]10.9: Move `BudgetQueryService` tests to `tests/Feature/` if DB is needed (Unit tests should NOT use RefreshDatabase per project convention — use Feature tests for DB-dependent tests)

- [x] Task 11: Run Full Test Suite (AC: all)
  - [x]11.1: Run `sail artisan test` — all 172 existing + ~10 new tests pass with zero regressions
  - [x]11.2: Verify TypeScript compiles cleanly: `sail bun run build`
  - [x]11.3: Verify `formatAmount` works correctly for edge cases: 0 cents → `"0,00 €"`, negative cents, large amounts with thousands separator

## Dev Notes

### Architecture Overview for This Story

This story introduces the first user-facing data display in the app. The key components are:

```
DashboardController::index(Request $request)
  ├─ Parse ?month=YYYY-MM param (default: current month)
  ├─ Call BudgetQueryService::getMonthlyData($year, $month)
  │   ├─ Eloquent query: JOIN transactions + categories
  │   ├─ GROUP BY category, filter by month
  │   ├─ Separate income vs. expense aggregation
  │   └─ Return formatted array
  └─ Return Inertia::render('dashboard', [...camelCase props...])

React Dashboard.tsx (Inertia page)
  ├─ SummaryWidget (income/expenses summary cards)
  └─ CategoryCard list (sorted by |amount| DESC)
       └─ formatAmount.ts (French locale currency formatting)
```

### BudgetQueryService Implementation

**Complete implementation guide for `app/Services/BudgetQueryService.php`:**

```php
<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class BudgetQueryService
{
    public function getMonthlyData(int $year, int $month, ?string $accountType = null): array
    {
        // Category totals query
        $query = Transaction::query()
            ->select([
                'categories.id',
                'categories.name',
                'categories.slug',
                'categories.color',
                DB::raw('SUM(transactions.amount_cents) as total_amount'),
                DB::raw('COUNT(transactions.id) as transaction_count'),
            ])
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereYear('transactions.date', $year)
            ->whereMonth('transactions.date', $month)
            ->groupBy('categories.id', 'categories.name', 'categories.slug', 'categories.color')
            ->orderByRaw('ABS(SUM(transactions.amount_cents)) DESC');

        // Account type filter (for Story 3.3 — optional now, used later)
        if ($accountType !== null) {
            $query->join('accounts', 'transactions.account_id', '=', 'accounts.id')
                  ->where('accounts.type', $accountType);
        }

        $rows = $query->get();

        $categoryTotals = $rows->map(fn ($row) => [
            'id'               => $row->id,
            'name'             => $row->name,
            'slug'             => $row->slug,
            'color'            => $row->color,
            'amountCents'      => (int) $row->total_amount,
            'transactionCount' => (int) $row->transaction_count,
        ])->values()->toArray();

        // Income: sum of 'income' category transactions for the month
        $incomeRow = $rows->firstWhere('slug', 'income');
        $incomeCents = $incomeRow ? (int) $incomeRow->total_amount : 0;

        // Expenses: absolute value of all non-income amounts (negative = expense)
        $expensesCents = abs(
            $rows->filter(fn ($r) => $r->slug !== 'income')
                 ->sum(fn ($r) => (int) $r->total_amount)
        );

        return compact('categoryTotals', 'incomeCents', 'expensesCents');
    }
}
```

**Key design decisions:**
- `whereYear()`/`whereMonth()` are Laravel Eloquent scopes that compile to SQL YEAR/MONTH functions — correct for PostgreSQL.
- `orderByRaw('ABS(SUM(transactions.amount_cents)) DESC')` — orders by absolute amount so both income (positive) and expenses (negative) compete on magnitude.
- The `$accountType` parameter is added for Story 3.3's account switching but defaults to null (no filtering) now. This avoids the accounts JOIN for the MVP path.
- Income and expenses are computed from the same `$rows` collection to avoid extra DB queries.

### DashboardController Implementation

```php
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
        // Parse month param (default: current month)
        $monthParam = $request->query('month', now()->format('Y-m'));

        // Validate format, fall back to current month on invalid input
        if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
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
```

### formatAmount.ts Implementation

```typescript
// resources/js/lib/formatAmount.ts

const formatter = new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

/**
 * Format integer cents to French locale currency string.
 * Examples: 52049 → "520,49 €", -5000 → "-50,00 €", 0 → "0,00 €"
 */
export function formatAmount(cents: number): string {
    return formatter.format(cents / 100);
}

/**
 * Format integer cents as absolute (positive) currency string.
 * Used for displaying expense totals that are stored as negative values.
 * Example: -52049 → "520,49 €"
 */
export function formatAbsAmount(cents: number): string {
    return formatter.format(Math.abs(cents) / 100);
}
```

**Important:** The `Intl.NumberFormat` instance is created once at module level (not inside the function) for performance — avoids re-instantiation on every call.

**Output format note:** `Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' })` produces `"520,49 €"` with a narrow-no-break-space (`\u202f`) between the number and the `€` sign. This is correct French locale behavior and matches the UX spec. The narrow-no-break-space also provides `tabular-nums` alignment behavior in practice.

### TypeScript Types Location

Add new file `resources/js/types/budget.ts` (following the existing pattern of `auth.ts`, `ui.ts` in the same folder):

```typescript
// resources/js/types/budget.ts
export type CategoryTotal = {
    id: number;
    name: string;
    slug: string;
    color: string; // hex color, e.g. "#A3E635"
    amountCents: number; // signed: negative for expenses (e.g. -5042 = 50.42€ expense)
    transactionCount: number;
};

export type DashboardProps = {
    categoryTotals: CategoryTotal[];
    incomeCents: number;    // sum of income-category transactions (positive)
    expensesCents: number;  // absolute sum of all other categories (always positive)
    currentMonth: string;   // "YYYY-MM" format, e.g. "2026-02"
};
```

Then add `export type * from './budget';` to `resources/js/types/index.ts`.

### SummaryWidget Component

```tsx
// resources/js/components/SummaryWidget.tsx
import { formatAmount } from '@/lib/formatAmount';

type Props = {
    incomeCents: number;
    expensesCents: number;
};

export default function SummaryWidget({ incomeCents, expensesCents }: Props) {
    return (
        <div className="flex gap-3">
            <div className="flex-1 bg-surface rounded-xl p-4">
                <p className="text-xs text-text-secondary mb-1">Income</p>
                <p className="text-lg font-semibold tabular-nums text-[#10B981]">
                    {formatAmount(incomeCents)}
                </p>
            </div>
            <div className="flex-1 bg-surface rounded-xl p-4">
                <p className="text-xs text-text-secondary mb-1">Expenses</p>
                <p className="text-lg font-semibold tabular-nums text-[#FB7185]">
                    {formatAmount(expensesCents)}
                </p>
            </div>
        </div>
    );
}
```

**Note:** `incomeCents` is already a positive sum (income transactions). `expensesCents` is the absolute value computed by `BudgetQueryService` (always positive). Both are formatted with `formatAmount` (not `formatAbsAmount`) since they're already positive values.

### CategoryCard Component

```tsx
// resources/js/components/CategoryCard.tsx
import { formatAbsAmount } from '@/lib/formatAmount';

type Props = {
    name: string;
    color: string;
    amountCents: number;  // signed (negative = expense)
    transactionCount: number;
};

export default function CategoryCard({ name, color, amountCents, transactionCount }: Props) {
    return (
        <div className="flex items-center gap-4 bg-surface rounded-xl p-4">
            {/* Color icon: 48px rounded square */}
            <div
                className="w-12 h-12 rounded-xl flex-shrink-0"
                style={{ backgroundColor: color }}
                aria-hidden="true"
            />

            {/* Category info */}
            <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-white truncate">{name}</p>
                <p className="text-xs text-text-secondary">
                    {transactionCount} transaction{transactionCount !== 1 ? 's' : ''}
                </p>
            </div>

            {/* Amount */}
            <p
                className="text-lg font-semibold tabular-nums ml-auto"
                style={{ color: color }}
            >
                {formatAbsAmount(amountCents)}
            </p>
        </div>
    );
}
```

**Note on amount display:** `formatAbsAmount` is used because expenses are stored as negative cents but displayed as positive values (e.g., Food spend of `-5042` displays as `"50,42 €"`, not `"-50,42 €"`). The Income category has positive `amountCents` so `Math.abs()` has no effect on it.

### Dashboard.tsx Full Implementation

```tsx
// resources/js/pages/dashboard.tsx
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import SummaryWidget from '@/components/SummaryWidget';
import CategoryCard from '@/components/CategoryCard';
import type { CategoryTotal, DashboardProps } from '@/types';

function formatMonthLabel(month: string): string {
    // "2026-02" → "February 2026"
    const date = new Date(`${month}-01`);
    return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
}

export default function Dashboard({ categoryTotals, incomeCents, expensesCents, currentMonth }: DashboardProps) {
    return (
        <AppLayout>
            <Head title="Budget" />
            <div className="py-6 space-y-5">
                <SummaryWidget incomeCents={incomeCents} expensesCents={expensesCents} />

                {categoryTotals.length === 0 ? (
                    <div className="rounded-xl bg-surface p-6 text-center">
                        <p className="text-sm text-text-secondary">
                            No transactions for {formatMonthLabel(currentMonth)}.
                            Import your first CSV to get started.
                        </p>
                    </div>
                ) : (
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
                )}
            </div>
        </AppLayout>
    );
}
```

### Testing Strategy

**Feature tests in `tests/Feature/DashboardTest.php` — extend existing file:**

The existing tests (`test_guests_are_redirected_to_the_login_page` and `test_authenticated_users_can_visit_the_dashboard`) continue to pass because `DashboardController@index` still:
1. Requires auth (same middleware)
2. Returns 200 OK for authenticated users

New tests require:
- `use Inertia\Testing\AssertableInertia as Assert;`
- `use Database\Seeders\CategorySeeder;` — seed categories to have valid category_id references
- `use App\Models\{Account, Category, Import, Transaction, User};`

**Critical: Seed categories before creating transactions.** Transactions must have a valid `category_id` FK. The `CategorySeeder` creates the 14 predefined categories. Seed with `$this->seed(CategorySeeder::class)` in each test that creates transactions.

**Test helper pattern:**
```php
private function createTestTransaction(User $user, string $categorySlug, int $amountCents, string $date): Transaction
{
    $account = Account::factory()->create(['type' => 'personal']);
    $category = Category::where('slug', $categorySlug)->first();
    $import = Import::factory()->create();

    return Transaction::factory()->create([
        'amount_cents' => $amountCents,
        'date'         => $date,
        'category_id'  => $category->id,
        'account_id'   => $account->id,
        'import_id'    => $import->id,
    ]);
}
```

**Sample test for AC1 (sorting):**
```php
public function test_category_totals_are_sorted_by_absolute_amount_descending()
{
    $this->seed(CategorySeeder::class);
    $user = User::factory()->create();

    // Food: 500€, Restaurants: 200€, Transport: 800€
    $this->createTestTransaction($user, 'food', -50000, '2026-02-10');
    $this->createTestTransaction($user, 'restaurants', -20000, '2026-02-15');
    $this->createTestTransaction($user, 'transport', -80000, '2026-02-20');

    $this->actingAs($user)
        ->get(route('dashboard', ['month' => '2026-02']))
        ->assertInertia(fn (Assert $page) =>
            $page->component('dashboard')
                 ->where('categoryTotals.0.slug', 'transport')
                 ->where('categoryTotals.1.slug', 'food')
                 ->where('categoryTotals.2.slug', 'restaurants')
        );
}
```

**Note on Inertia testing:** Use `use Inertia\Testing\AssertableInertia as Assert;` for the fluent assertion API. The `assertInertia()` method is provided by Inertia's test helpers and works with the standard Laravel test response.

### Route Update

Change `routes/web.php` line 13-15:
```php
// BEFORE:
Route::get('dashboard', function () {
    return Inertia::render('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// AFTER:
Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
```

Add at top of web.php: `use App\Http\Controllers\DashboardController;`

The `use Inertia\Inertia;` import should stay in web.php because the `'/'` route still uses it for `Inertia::render('welcome', [...])`.

### Tailwind CSS Classes Used

The following Tailwind utility classes must exist in the project (all should already be configured from Story 1.3):

| Class | Usage | Defined in |
|-------|-------|-----------|
| `bg-background` | App background (#0A0A0B) | Story 1.3 Tailwind config |
| `bg-surface` | Card backgrounds (#1C1C1E) | Story 1.3 Tailwind config |
| `text-text-secondary` | Secondary text (#A1A1AA) | Story 1.3 Tailwind config |
| `text-white` | Primary text | Tailwind built-in |
| `tabular-nums` | Number alignment | Tailwind built-in (`font-variant-numeric: tabular-nums`) |
| `rounded-xl` | 12px border radius | Tailwind built-in |
| `w-12 h-12` | 48px icon square | Tailwind built-in |

**If `bg-surface` or `text-text-secondary` are not defined** (check `tailwind.config.ts` or CSS file), add them. Story 1.3 should have configured these. If they're defined via CSS custom properties (`--surface`, `--text-secondary`), the Tailwind classes should reference them.

### Previous Story Intelligence

**From Story 2.4 (AI Auto-Categorization — direct predecessor):**
- 172 tests, 514 assertions, zero regressions — maintain this standard
- `Category` model: 14 categories seeded by `CategorySeeder`, each with `slug`, `color`, `name`, `parent_id` (null for top-level)
- `Transaction` model: `amount_cents` (integer), `date` (Carbon), `category_id`, `account_id`, `import_id`
- `Account` model has `personal()` and `professional()` factory states
- `Import` model has factory available
- All commands run inside Sail: `sail artisan test`, `sail bun run build`
- One commit per story convention
- The `laravel/ai` SDK is installed — no interaction needed for this story

**From Story 1.3 (Visual Foundation):**
- Dark theme CSS tokens are configured: `background` (#0A0A0B), `surface` (#1C1C1E), `surface-elevated` (#2C2C2E)
- `text-secondary` (#A1A1AA) and `text-tertiary` (#71717A) are defined
- Inter variable font is configured
- Category colors are stored in DB — never hardcode in TypeScript (read from Inertia props)
- `max-w-[480px] mx-auto` is the centered layout constraint in `AppLayout`

**From Story 2.3 (Import Pipeline):**
- `Transaction::factory()` exists and works
- `Import::factory()` exists and works
- The `transaction_count` field on Import records the count of transactions

**Critical note from Story 1.2:**
- `CategorySeeder` must be run in tests that query `categories` table — transactions need valid `category_id` FK
- 14 categories confirmed: food, restaurants, transport, housing, entertainment, shopping, health, subscriptions, education, travel, insurance, income, professional, other
- All are top-level (parent_id = null) — subcategory support is deferred to Story 3.1

### Git Intelligence

Recent commits (one per story):
- `0035160` Story 2.4: AI auto-categorization engine with hybrid merchant matching + Gemini pipeline
- `a37277f` Story 2.3: Import pipeline with SHA-256 deduplication
- `01f6da1` Story 2.2: Revolut CSV parser (Personal + Business formats)
- `5e990ad` Story 2.1: CSV parser framework and Banque Populaire support
- `520aee7` Story 1.4: Production deployment with HTTPS and monitoring

**Established patterns:**
- One commit per story
- All commands via Sail (`sail artisan test`, `sail bun run build`)
- Tests must pass with zero regressions before committing
- Feature tests: use `RefreshDatabase` trait + seed required data
- Unit tests (no DB): pure logic only (but `BudgetQueryService` needs DB, so use Feature tests)

### Testing Requirements Summary

**Feature tests (extend `tests/Feature/DashboardTest.php`):**
- ~10 new tests covering all ACs
- Use `RefreshDatabase`, seed `CategorySeeder`
- Use `AssertableInertia` for prop assertions
- Test factory: `Account::factory()`, `Transaction::factory()`, `Import::factory()`, `Category::where('slug', ...)->first()`
- Do NOT use `CsvImportService` for test setup — create models directly via factories

**Unit tests for `BudgetQueryService` → move to `tests/Feature/BudgetQueryServiceTest.php`:**
- Needs DB (RefreshDatabase) because it runs Eloquent queries
- Per project convention: if a test needs DB, it goes in `tests/Feature/`, not `tests/Unit/`
- Reference: Story 2.4 placed `MerchantMatchingServiceTest.php` in `tests/Unit/` because it uses `RefreshDatabase` — but this was discussed as slightly inconsistent. Follow the real usage: if DB is needed, `Feature/`.

### Architecture Compliance

| Requirement | Implementation |
|-------------|----------------|
| Inertia for web UI [arch#API Patterns] | `DashboardController` returns `Inertia::render()`, never JSON |
| Inertia props in camelCase [arch#Naming Patterns] | `categoryTotals`, `incomeCents`, `expensesCents`, `currentMonth` |
| Services are stateless [arch#Service Boundary] | `BudgetQueryService` injected via constructor, no state |
| `BudgetQueryService` is shared layer [arch#Service Boundary] | Added to `app/Services/` alongside existing services |
| Amount handling [arch#Format Patterns] | `amountCents` integer throughout; `formatAmount.ts` formats at display layer only |
| Category colors from DB [arch#Enforcement] | Colors come from `categories.color` via Inertia prop — no frontend constants |
| Sub-300ms navigation [NFR1] | Inertia server-renders the page; PostgreSQL query with GROUP BY is fast for single-user data volume |
| `router.get` with `preserveState: true` for month nav [arch#Validation] | Not yet implemented (Story 3.2), but DashboardController accepts `?month=` param ready for it |

### What This Story Does NOT Include

- **MonthStrip component** — Story 3.2 (Month Navigation). DashboardController accepts `?month=` but no UI strip yet.
- **Account picker dropdown** — Story 3.3 (Account Management). BudgetQueryService accepts `$accountType` param but no UI toggle yet.
- **Category drill-down** — Story 3.1 (Category Drill-Down with Color Takeover). CategoryCard is NOT clickable in this story.
- **Import UI (drag-and-drop, file picker)** — Story 2.6 (Import User Interface). The Dashboard shows an empty state message but no ImportDropZone/EmptyState components.
- **Import history / delete import** — Story 4.3.
- **Import confirmation dialog** — Story 2.6.

### Project Structure Notes

**New files to create:**
```
app/
├── Http/Controllers/
│   └── DashboardController.php              (NEW)
├── Services/
│   └── BudgetQueryService.php               (NEW)
resources/js/
├── lib/
│   └── formatAmount.ts                      (NEW)
├── types/
│   └── budget.ts                            (NEW domain types)
├── components/
│   ├── SummaryWidget.tsx                    (NEW)
│   └── CategoryCard.tsx                     (NEW)
tests/
└── Feature/
    ├── DashboardTest.php                    (EXTEND — add new tests, keep existing 2)
    └── BudgetQueryServiceTest.php           (NEW — uses RefreshDatabase)
```

**Modified files:**
```
routes/web.php                               (replace dashboard closure with DashboardController)
resources/js/pages/dashboard.tsx             (replace placeholder with full implementation)
resources/js/types/index.ts                  (add export for budget.ts)
```

**Aligns with architecture directory structure:**
- Controllers in `app/Http/Controllers/` (flat) [Source: architecture.md#Structure Patterns]
- Services in `app/Services/` (flat) [Source: architecture.md#Structure Patterns]
- Components in `resources/js/components/` [Source: architecture.md#Structure Patterns]
- Utilities in `resources/js/lib/` [Source: architecture.md#Structure Patterns]
- Domain types in `resources/js/types/` [Source: architecture.md#Structure Patterns]

### References

- Epic 2 story requirements: [Source: _bmad-output/planning-artifacts/epics.md#Story 2.5]
- BudgetQueryService design: [Source: _bmad-output/planning-artifacts/architecture.md#Service Boundary]
- Inertia data flow: [Source: _bmad-output/planning-artifacts/architecture.md#Communication Patterns]
- Amount handling: [Source: _bmad-output/planning-artifacts/architecture.md#Format Patterns]
- Category colors: [Source: database/seeders/CategorySeeder.php]
- Visual design (dark theme, surface layers): [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Color System]
- CategoryCard design (Motivated-inspired): [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Design Direction Decision]
- SummaryWidget design (Split View pattern): [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Design Direction Decision]
- Spacing/layout tokens: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Spacing & Layout Foundation]
- Typography tokens: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Typography System]
- Tailwind config tokens: [Source: story 1.3 implementation — bg-surface, text-text-secondary confirmed in app-layout.tsx]
- Previous story 2.4: [Source: _bmad-output/implementation-artifacts/2-4-ai-auto-categorization-engine.md]
- Transaction model: [Source: app/Models/Transaction.php]
- Category model: [Source: app/Models/Category.php]

## Dev Agent Record

### Agent Model Used

claude-opus-4-6

### Debug Log References

No issues encountered. All tests passed on first run (187 tests, 644 assertions, 0 regressions). TypeScript build clean.

### Completion Notes List

- **Task 1:** Created `BudgetQueryService` with `getMonthlyData()` method — Eloquent query joining transactions+categories, grouped by category, ordered by ABS(SUM) DESC. Income/expenses computed from same query results.
- **Task 2:** Created `DashboardController` with constructor-injected `BudgetQueryService`, `?month=YYYY-MM` param parsing with regex validation and fallback to current month.
- **Task 3:** Updated `routes/web.php` — replaced dashboard closure with `DashboardController::index`, added import. Kept `Inertia\Inertia` import for welcome route.
- **Task 4:** Created `formatAmount.ts` with module-level `Intl.NumberFormat('fr-FR')` instance. Exports `formatAmount()` and `formatAbsAmount()` for French locale EUR formatting.
- **Task 5:** Created `budget.ts` types (`CategoryTotal`, `DashboardProps`) and exported from `types/index.ts`.
- **Task 6:** Created `SummaryWidget` — two side-by-side cards (Income emerald #10B981, Expenses rose #FB7185) with `formatAmount`.
- **Task 7:** Created `CategoryCard` — 48px colored square, category name, transaction count, amount in category color using `formatAbsAmount`.
- **Task 8:** Implemented `Dashboard.tsx` — `AppLayout` > `SummaryWidget` > category card list with empty state message.
- **Task 9:** Extended `DashboardTest.php` with 8 new feature tests (kept 2 existing). Tests cover: prop shape, sorting, income sum, expense abs value, month filtering, month param, invalid param fallback, empty state.
- **Task 10:** Created `BudgetQueryServiceTest.php` in `tests/Feature/` (needs DB). 7 tests covering: structure, field presence, exclusion of empty categories, income sum, expenses abs, sorting, month filtering.
- **Task 11:** Full suite: 187 tests, 644 assertions, 0 failures. TypeScript build clean.

### File List

**New files:**
- app/Services/BudgetQueryService.php
- app/Http/Controllers/DashboardController.php
- resources/js/lib/formatAmount.ts
- resources/js/types/budget.ts
- resources/js/components/SummaryWidget.tsx
- resources/js/components/CategoryCard.tsx
- tests/Feature/BudgetQueryServiceTest.php

**Modified files:**
- routes/web.php
- resources/js/pages/dashboard.tsx
- resources/js/types/index.ts
- tests/Feature/DashboardTest.php

### Review Follow-ups (AI)

- [ ] [AI-Review][LOW] Add Vitest unit tests for `formatAmount.ts` edge cases (0 cents, negative cents, large amounts) [resources/js/lib/formatAmount.ts]
- [ ] [AI-Review][LOW] BudgetQueryService not scoped to authenticated user — acceptable for single-user but note for future multi-user support [app/Services/BudgetQueryService.php]

### Change Log

- 2026-02-20: Story 2.5 implemented — Financial overview dashboard with BudgetQueryService, DashboardController, SummaryWidget, CategoryCard, Dashboard page, formatAmount utility, and 15 new tests (187 total, 0 regressions)
- 2026-02-20: Code review fixes — CategoryCard amount font 18px→24px (AC2), category name font-medium→font-normal (AC2), month param regex tightened to validate 01-12 range, formatMonthLabel timezone bug fixed
