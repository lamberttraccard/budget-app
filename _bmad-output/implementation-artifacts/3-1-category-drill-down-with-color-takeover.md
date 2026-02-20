# Story 3.1: Category Drill-Down with Color Takeover

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As Lambert,
I want to tap a category card and see its subcategories and individual transactions in a color-dominant detail view,
so that I can understand exactly what makes up each spending category.

## Acceptance Criteria

1. **Given** I am on the Dashboard viewing category cards, **When** I tap a category card, **Then** I navigate to the Categories/Show page with a full Inertia page visit (FR14).

2. **Given** the drill-down page loads, **When** I see the header, **Then** the category's accent color floods the header background (ColorHeader) with: large centered icon (64px), category name, total amount (36px bold), and transaction count (FR14).

3. **Given** the category has subcategories, **When** the drill-down page renders, **Then** I see SubcategoryRows grouped in a surface-colored card, each showing subcategory name, amount, and a chevron for further drill-down (FR14).

4. **Given** I tap a subcategory row, **When** the subcategory expands or navigates, **Then** I see individual TransactionRows for that subcategory: date (short format, e.g. "Feb 19"), merchant name, and amount (FR15).

5. **Given** the drill-down is displayed, **When** I tap the close/back button, **Then** I return to the Dashboard with my previous scroll position preserved.

6. **Given** the drill-down loads, **When** I measure perceived latency, **Then** navigation feels instant, under 300ms (NFR1).

## Tasks / Subtasks

- [x] Task 1: Extend BudgetQueryService with category detail methods (AC: 2, 3, 4)
  - [x] 1.1: Add `getCategoryDetail(int $categoryId, int $year, int $month)` method returning category info, subcategory totals, and transactions
  - [x] 1.2: Query subcategories via `Category::where('parent_id', $categoryId)` with aggregated transaction totals for the month
  - [x] 1.3: Query transactions for the category (and its subcategories) for the given month, ordered by date descending
  - [x] 1.4: Return structured array with: category (id, name, color, slug), subcategories (name, amountCents, transactionCount, id), transactions grouped by subcategory or flat list, totalAmountCents, transactionCount

- [x] Task 2: Create CategoryController with show method (AC: 1, 2, 3, 4)
  - [x] 2.1: Create `app/Http/Controllers/CategoryController.php`
  - [x] 2.2: Inject `BudgetQueryService` via constructor
  - [x] 2.3: Implement `show(Category $category, Request $request)` using route model binding
  - [x] 2.4: Accept `?month=YYYY-MM` query parameter (default to current month), validate format
  - [x] 2.5: Call `BudgetQueryService::getCategoryDetail()` and return `Inertia::render('categories/show', [...])`
  - [x] 2.6: Pass props in camelCase: `category`, `subcategories`, `transactions`, `totalAmountCents`, `transactionCount`, `currentMonth`

- [x] Task 3: Add route for category show page (AC: 1)
  - [x] 3.1: Add `Route::get('categories/{category}', [CategoryController::class, 'show'])` with auth+verified middleware, name `categories.show`
  - [x] 3.2: Add `use App\Http\Controllers\CategoryController;` import in `routes/web.php`

- [x] Task 4: Add TypeScript types for category detail page (AC: 2, 3, 4)
  - [x] 4.1: Add `TransactionItem` type to `resources/js/types/budget.ts`: `{ id, date, description, amountCents }`
  - [x] 4.2: Add `SubcategoryTotal` type: `{ id, name, amountCents, transactionCount }`
  - [x] 4.3: Add `CategoryShowProps` type: `{ category: { id, name, color, slug }, subcategories, transactions, totalAmountCents, transactionCount, currentMonth }`

- [x] Task 5: Create formatDate utility (AC: 4)
  - [x] 5.1: Create `resources/js/lib/formatDate.ts` with `formatDate(isoDate: string): string` returning short format like "Feb 19"
  - [x] 5.2: Use `Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' })`

- [x] Task 6: Create ColorHeader component (AC: 2)
  - [x] 6.1: Create `resources/js/components/ColorHeader.tsx`
  - [x] 6.2: Props: `{ name, color, totalAmountCents, transactionCount, onBack }`
  - [x] 6.3: Full-width background using the category's accent color
  - [x] 6.4: Render: 64px colored icon square (darkened background), category name (20px semibold), total amount (36px bold) using `formatAbsAmount`, transaction count subtitle, back/close button (top-left)
  - [x] 6.5: Text uses dark color on light color backgrounds for contrast

- [x] Task 7: Create SubcategoryRow component (AC: 3)
  - [x] 7.1: Create `resources/js/components/SubcategoryRow.tsx`
  - [x] 7.2: Props: `{ name, amountCents, transactionCount, onClick }`
  - [x] 7.3: Render: subcategory name (15px medium), amount (16px semibold tabular-nums), chevron icon (right side)
  - [x] 7.4: Grouped inside a surface-colored card with rounded corners

- [x] Task 8: Create TransactionRow component (AC: 4)
  - [x] 8.1: Create `resources/js/components/TransactionRow.tsx`
  - [x] 8.2: Props: `{ id, date, description, amountCents }`
  - [x] 8.3: Render: date in short format (48px fixed width, tertiary text, 12px), merchant name (14px regular, flexible width, truncate), amount (14px semibold tabular-nums, right-aligned)
  - [x] 8.4: Minimum row height 44px for tap targets
  - [x] 8.5: Row is NOT tappable yet (category correction is Story 4.1)

- [x] Task 9: Create Categories/Show page (AC: 1, 2, 3, 4, 5)
  - [x] 9.1: Create `resources/js/pages/categories/show.tsx`
  - [x] 9.2: Render ColorHeader with category data and back button (`router.visit('/dashboard', { preserveState: true })` or `window.history.back()`)
  - [x] 9.3: If subcategories exist, render them in a surface-colored grouped card with SubcategoryRow components
  - [x] 9.4: Render all transactions in a surface-colored card using TransactionRow components, sorted by date descending
  - [x] 9.5: Handle subcategory click → filter visible transactions to that subcategory (client-side toggle, not page navigation)
  - [x] 9.6: Use AppLayout wrapper for consistent max-width and margins

- [x] Task 10: Make CategoryCard clickable on Dashboard (AC: 1)
  - [x] 10.1: Add `id` prop to `CategoryCard` component
  - [x] 10.2: Wrap the card with Inertia `Link` to `/categories/{id}?month={currentMonth}`
  - [x] 10.3: Add hover state: `hover:bg-surface-elevated` transition
  - [x] 10.4: Update Dashboard.tsx to pass `id` and `currentMonth` to CategoryCard

- [x] Task 11: Write Feature Tests (AC: 1-6)
  - [x] 11.1: Create `tests/Feature/CategoryControllerTest.php`
  - [x] 11.2: Test show with valid category returns Inertia response with correct props
  - [x] 11.3: Test show with month parameter filters transactions to that month
  - [x] 11.4: Test show with category that has subcategories returns subcategory data
  - [x] 11.5: Test show with category that has no transactions returns empty arrays
  - [x] 11.6: Test show requires authentication (redirect to login)
  - [x] 11.7: Test show with invalid category ID returns 404
  - [x] 11.8: Test show with invalid month format defaults to current month
  - [x] 11.9: Run `sail artisan test` — all existing + new tests pass with zero regressions
  - [x] 11.10: Verify TypeScript compiles cleanly: `sail bun run build`

## Dev Notes

### Architecture Overview for This Story

This story creates the category drill-down view — the second level of the app's navigation hierarchy (Dashboard → Category → Transactions). It's the first story in Epic 3 (Spending Exploration) and introduces the Motivated-style "color takeover" visual pattern.

The backend work is minimal: one new controller method leveraging the existing `BudgetQueryService`, plus one new query method. The frontend work is the core: three new components (ColorHeader, SubcategoryRow, TransactionRow), one new page (Categories/Show), and making CategoryCard clickable.

```
Dashboard (existing)
  │
  ├─ CategoryCard [tap] → Inertia page visit
  │
  └─ Categories/Show page (NEW)
      ├─ ColorHeader (accent color flood, back button)
      ├─ SubcategoryRows (if subcategories exist)
      └─ TransactionRows (individual transactions)
```

**Data flow:**
```
CategoryCard tap → router.visit('/categories/{id}?month=2026-02')
  → CategoryController@show
    → BudgetQueryService::getCategoryDetail($categoryId, $year, $month)
      → Eloquent queries (category, subcategories, transactions)
    → Inertia::render('categories/show', [...props in camelCase])
  → Categories/Show.tsx renders with ColorHeader + rows
```

**Key design decisions:**
- **Full Inertia page visit** (not partial reload): The drill-down is a distinct page with its own URL (`/categories/{id}?month=YYYY-MM`). This gives back-button navigation for free and a clean URL structure.
- **Client-side subcategory filtering**: When a subcategory row is tapped, transactions filter on the client (toggle visibility). No server round-trip needed for this interaction — all transaction data is already loaded.
- **No subcategories currently seeded**: The 14 top-level categories have no children. The SubcategoryRow component is built and wired up, but will show nothing until subcategories are created. All transactions display in a flat list under the category.
- **Back button preserves scroll position**: Using `window.history.back()` preserves the Dashboard's scroll position via Inertia's built-in scroll management. No custom scroll restoration needed.
- **Month parameter forwarded**: The `?month=` param from the Dashboard is forwarded to the drill-down so the user sees the same month's data. This prevents a jarring context switch.

### CategoryController Implementation

```php
<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\BudgetQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(private readonly BudgetQueryService $budgetQueryService) {}

    public function show(Category $category, Request $request): Response
    {
        $monthParam = $request->query('month', now()->format('Y-m'));

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthParam)) {
            $monthParam = now()->format('Y-m');
        }

        [$year, $month] = array_map('intval', explode('-', $monthParam));

        $data = $this->budgetQueryService->getCategoryDetail($category->id, $year, $month);

        return Inertia::render('categories/show', [
            'category' => [
                'id'   => $category->id,
                'name' => $category->name,
                'color' => $category->color,
                'slug' => $category->slug,
            ],
            'subcategories'    => $data['subcategories'],
            'transactions'     => $data['transactions'],
            'totalAmountCents' => $data['totalAmountCents'],
            'transactionCount' => $data['transactionCount'],
            'currentMonth'     => $monthParam,
        ]);
    }
}
```

**Implementation notes:**
- Uses **route model binding** (`Category $category`) — Laravel auto-resolves the model from the URL parameter and returns 404 if not found.
- Month validation identical to `DashboardController` — keep consistent.
- Props follow camelCase convention per architecture spec.

### BudgetQueryService Extension

```php
public function getCategoryDetail(int $categoryId, int $year, int $month): array
{
    $category = Category::findOrFail($categoryId);

    // Get subcategory IDs (children of this category)
    $subcategoryIds = Category::where('parent_id', $categoryId)->pluck('id');

    // All category IDs to query (parent + children)
    $allCategoryIds = collect([$categoryId])->merge($subcategoryIds);

    // Get subcategory totals (only if subcategories exist)
    $subcategories = [];
    if ($subcategoryIds->isNotEmpty()) {
        $subcategories = Transaction::query()
            ->select([
                'categories.id',
                'categories.name',
                DB::raw('SUM(transactions.amount_cents) as total_amount'),
                DB::raw('COUNT(transactions.id) as transaction_count'),
            ])
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereIn('transactions.category_id', $subcategoryIds)
            ->whereYear('transactions.date', $year)
            ->whereMonth('transactions.date', $month)
            ->groupBy('categories.id', 'categories.name')
            ->orderByRaw('ABS(SUM(transactions.amount_cents)) DESC')
            ->get()
            ->map(fn ($row) => [
                'id'               => $row->id,
                'name'             => $row->name,
                'amountCents'      => (int) $row->total_amount,
                'transactionCount' => (int) $row->transaction_count,
            ])
            ->values()
            ->toArray();
    }

    // Get all transactions for this category (and subcategories)
    $transactions = Transaction::query()
        ->whereIn('category_id', $allCategoryIds)
        ->whereYear('date', $year)
        ->whereMonth('date', $month)
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->get()
        ->map(fn (Transaction $t) => [
            'id'          => $t->id,
            'date'        => $t->date->toDateString(),
            'description' => $t->description,
            'amountCents' => $t->amount_cents,
            'categoryId'  => $t->category_id,
        ])
        ->values()
        ->toArray();

    $totalAmountCents = (int) collect($transactions)->sum('amountCents');
    $transactionCount = count($transactions);

    return compact('subcategories', 'transactions', 'totalAmountCents', 'transactionCount');
}
```

**Key decisions:**
- Queries include both the parent category AND its subcategories — a category like "Food" shows all transactions categorized as "Food" directly plus any in Food subcategories.
- Transactions are ordered by date descending (most recent first), then by ID descending for stable sort within the same date.
- `categoryId` is included on each transaction to support client-side subcategory filtering.
- The `totalAmountCents` is computed from the actual transactions returned (not a separate query) for consistency.

### Route Addition

```php
// routes/web.php — add after import routes:
Route::get('categories/{category}', [CategoryController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('categories.show');
```

Add at top: `use App\Http\Controllers\CategoryController;`

### TypeScript Types

Add to `resources/js/types/budget.ts`:

```typescript
export type TransactionItem = {
    id: number;
    date: string;          // ISO date "2026-02-19"
    description: string;   // Merchant name
    amountCents: number;
    categoryId: number;
};

export type SubcategoryTotal = {
    id: number;
    name: string;
    amountCents: number;
    transactionCount: number;
};

export type CategoryShowProps = {
    category: {
        id: number;
        name: string;
        color: string;
        slug: string;
    };
    subcategories: SubcategoryTotal[];
    transactions: TransactionItem[];
    totalAmountCents: number;
    transactionCount: number;
    currentMonth: string;
};
```

### formatDate Utility

```typescript
// resources/js/lib/formatDate.ts
const formatter = new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
});

export function formatDate(isoDate: string): string {
    return formatter.format(new Date(isoDate + 'T00:00:00'));
}
```

**Notes:**
- Appending `T00:00:00` prevents timezone offset issues when parsing date-only strings.
- Output: `"Feb 19"` — matches UX spec's short date format.
- Uses `en-US` locale (consistent with existing `formatMonthLabel` in Dashboard).

### ColorHeader Component

```tsx
// resources/js/components/ColorHeader.tsx
import { ArrowLeftIcon } from 'lucide-react';
import { formatAbsAmount } from '@/lib/formatAmount';

type Props = {
    name: string;
    color: string;
    totalAmountCents: number;
    transactionCount: number;
    onBack: () => void;
};

export default function ColorHeader({ name, color, totalAmountCents, transactionCount, onBack }: Props) {
    return (
        <div className="relative -mx-4 sm:-mx-8 px-4 sm:px-8 pt-4 pb-8" style={{ backgroundColor: color }}>
            {/* Back button */}
            <button
                type="button"
                onClick={onBack}
                className="absolute top-4 left-4 sm:left-8 flex items-center gap-1 text-sm font-medium text-black/70 hover:text-black"
            >
                <ArrowLeftIcon className="size-5" />
                Back
            </button>

            {/* Content centered */}
            <div className="flex flex-col items-center pt-8">
                {/* Category icon (colored square with darkened background) */}
                <div
                    className="w-16 h-16 rounded-2xl mb-4"
                    style={{ backgroundColor: 'rgba(0,0,0,0.15)' }}
                    aria-hidden="true"
                />

                <h1 className="text-xl font-semibold text-black/90">{name}</h1>

                <p className="mt-2 text-4xl font-bold tabular-nums text-black/90">
                    {formatAbsAmount(totalAmountCents)}
                </p>

                <p className="mt-1 text-sm text-black/60">
                    {transactionCount} transaction{transactionCount !== 1 ? 's' : ''}
                </p>
            </div>
        </div>
    );
}
```

**Design notes:**
- Negative margins (`-mx-4 sm:-mx-8`) break out of the AppLayout's padding to make the color header truly full-width. Padding re-applied inside.
- Text is dark (`text-black/90`, `text-black/60`) on the colored background for contrast, per UX spec.
- The 64px icon square uses `rgba(0,0,0,0.15)` darkened overlay on the accent color — a Motivated-style treatment.
- Back button positioned top-left, dark text with hover effect.

### SubcategoryRow Component

```tsx
// resources/js/components/SubcategoryRow.tsx
import { ChevronRightIcon } from 'lucide-react';
import { formatAbsAmount } from '@/lib/formatAmount';

type Props = {
    name: string;
    amountCents: number;
    transactionCount: number;
    onClick: () => void;
};

export default function SubcategoryRow({ name, amountCents, transactionCount, onClick }: Props) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-surface-elevated"
        >
            <div className="flex-1 min-w-0">
                <p className="text-[15px] font-medium text-white truncate">{name}</p>
                <p className="text-xs text-text-secondary">
                    {transactionCount} transaction{transactionCount !== 1 ? 's' : ''}
                </p>
            </div>
            <p className="text-base font-semibold tabular-nums text-white ml-auto">
                {formatAbsAmount(amountCents)}
            </p>
            <ChevronRightIcon className="size-4 text-text-tertiary flex-shrink-0" />
        </button>
    );
}
```

**Notes:**
- Minimum implicit height > 44px due to padding + content (tap target requirement).
- Chevron signals "tap to expand" per UX spec.
- `truncate` on name prevents overflow on long subcategory names.

### TransactionRow Component

```tsx
// resources/js/components/TransactionRow.tsx
import { formatAbsAmount } from '@/lib/formatAmount';
import { formatDate } from '@/lib/formatDate';

type Props = {
    date: string;
    description: string;
    amountCents: number;
};

export default function TransactionRow({ date, description, amountCents }: Props) {
    return (
        <div className="flex items-center gap-3 px-4 py-2.5 min-h-[44px]">
            <span className="w-12 flex-shrink-0 text-xs text-text-tertiary">
                {formatDate(date)}
            </span>
            <span className="flex-1 min-w-0 text-sm text-white truncate">
                {description}
            </span>
            <span className="text-sm font-semibold tabular-nums text-white ml-auto">
                {formatAbsAmount(amountCents)}
            </span>
        </div>
    );
}
```

**Notes:**
- Date width fixed at `w-12` (48px) per UX spec.
- Row is NOT tappable in this story — category correction comes in Story 4.1.
- `min-h-[44px]` enforces minimum touch target height.
- `truncate` on merchant name handles long descriptions.

### Categories/Show Page

```tsx
// resources/js/pages/categories/show.tsx
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import ColorHeader from '@/components/ColorHeader';
import SubcategoryRow from '@/components/SubcategoryRow';
import TransactionRow from '@/components/TransactionRow';
import type { CategoryShowProps, TransactionItem } from '@/types';

export default function CategoryShow({
    category,
    subcategories,
    transactions,
    totalAmountCents,
    transactionCount,
    currentMonth,
}: CategoryShowProps) {
    const [activeSubcategoryId, setActiveSubcategoryId] = useState<number | null>(null);

    const handleBack = () => {
        window.history.back();
    };

    const handleSubcategoryClick = (subcategoryId: number) => {
        setActiveSubcategoryId(
            activeSubcategoryId === subcategoryId ? null : subcategoryId
        );
    };

    // Filter transactions by active subcategory (client-side)
    const visibleTransactions: TransactionItem[] = activeSubcategoryId
        ? transactions.filter(t => t.categoryId === activeSubcategoryId)
        : transactions;

    return (
        <AppLayout>
            <Head title={category.name} />

            <ColorHeader
                name={category.name}
                color={category.color}
                totalAmountCents={totalAmountCents}
                transactionCount={transactionCount}
                onBack={handleBack}
            />

            <div className="py-5 space-y-4">
                {/* Subcategories section */}
                {subcategories.length > 0 && (
                    <div className="bg-surface rounded-xl overflow-hidden">
                        {subcategories.map((sub, index) => (
                            <div key={sub.id}>
                                {index > 0 && (
                                    <div className="mx-4 border-t border-white/5" />
                                )}
                                <SubcategoryRow
                                    name={sub.name}
                                    amountCents={sub.amountCents}
                                    transactionCount={sub.transactionCount}
                                    onClick={() => handleSubcategoryClick(sub.id)}
                                />
                            </div>
                        ))}
                    </div>
                )}

                {/* Transactions section */}
                {visibleTransactions.length > 0 ? (
                    <div className="bg-surface rounded-xl overflow-hidden">
                        {visibleTransactions.map((t, index) => (
                            <div key={t.id}>
                                {index > 0 && (
                                    <div className="mx-4 border-t border-white/5" />
                                )}
                                <TransactionRow
                                    date={t.date}
                                    description={t.description}
                                    amountCents={t.amountCents}
                                />
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="py-8 text-center">
                        <p className="text-sm text-text-tertiary">
                            No transactions for this category
                        </p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
```

**Key implementation notes:**
1. **Client-side subcategory filtering:** Tapping a SubcategoryRow toggles `activeSubcategoryId`. Transactions filter by `categoryId`. Tapping again deselects (shows all). No server round-trip.
2. **`window.history.back()`** for back navigation preserves Dashboard scroll position via Inertia's built-in scroll management.
3. **Subtle row dividers:** `border-t border-white/5` creates barely-visible separators between rows — matches the dark theme aesthetic.
4. **Empty state:** "No transactions for this category" in tertiary text when the category has no transactions for the selected month.
5. **Inertia page name:** `categories/show` matches the file path `resources/js/pages/categories/show.tsx`.

### Making CategoryCard Clickable

Update `resources/js/components/CategoryCard.tsx`:

```tsx
import { Link } from '@inertiajs/react';
import { formatAbsAmount } from '@/lib/formatAmount';

type Props = {
    id: number;
    name: string;
    color: string;
    amountCents: number;
    transactionCount: number;
    currentMonth: string;
};

export default function CategoryCard({ id, name, color, amountCents, transactionCount, currentMonth }: Props) {
    return (
        <Link
            href={`/categories/${id}?month=${currentMonth}`}
            className="flex items-center gap-4 bg-surface rounded-xl p-4 transition-colors hover:bg-surface-elevated"
        >
            <div
                className="w-12 h-12 rounded-xl flex-shrink-0"
                style={{ backgroundColor: color }}
                aria-hidden="true"
            />
            <div className="flex-1 min-w-0">
                <p className="text-sm font-normal text-white truncate">{name}</p>
                <p className="text-xs text-text-secondary">
                    {transactionCount} transaction{transactionCount !== 1 ? 's' : ''}
                </p>
            </div>
            <p
                className="text-2xl font-semibold tabular-nums ml-auto"
                style={{ color }}
            >
                {formatAbsAmount(amountCents)}
            </p>
        </Link>
    );
}
```

**Changes from current:**
- Wrapping element changes from `<div>` to `<Link>` (Inertia)
- New props: `id`, `currentMonth`
- Added `hover:bg-surface-elevated` transition
- `href` points to `/categories/{id}?month={currentMonth}`

Update `Dashboard.tsx` CategoryCard usage:

```tsx
<CategoryCard
    key={cat.id}
    id={cat.id}
    name={cat.name}
    color={cat.color}
    amountCents={cat.amountCents}
    transactionCount={cat.transactionCount}
    currentMonth={currentMonth}
/>
```

### Testing Strategy

**Feature tests in `tests/Feature/CategoryControllerTest.php`:**

All tests use `RefreshDatabase` trait and seed `CategorySeeder`. Create transactions via factories or direct DB insertion.

```php
// Test setup helper
private function createCategoryWithTransactions(string $slug, int $count = 3, string $month = '2026-02'): Category
{
    $this->seed(CategorySeeder::class);
    $category = Category::where('slug', $slug)->first();
    $account = Account::factory()->create(['type' => 'personal']);

    for ($i = 0; $i < $count; $i++) {
        Transaction::factory()->create([
            'category_id' => $category->id,
            'account_id' => $account->id,
            'date' => "2026-{$month}-" . str_pad($i + 1, 2, '0', STR_PAD_LEFT),
            'amount_cents' => -($i + 1) * 1000,
        ]);
    }

    return $category;
}
```

**Test cases:**
1. `test_show_returns_inertia_response_with_category_data` — verify props structure
2. `test_show_with_month_parameter_filters_transactions` — only transactions in specified month
3. `test_show_with_no_transactions_returns_empty_arrays` — empty state
4. `test_show_requires_authentication` — redirect to login
5. `test_show_with_invalid_category_returns_404` — route model binding
6. `test_show_with_invalid_month_defaults_to_current` — fallback behavior
7. `test_show_includes_subcategory_transactions` — if subcategories exist, their transactions are included
8. `test_show_returns_correct_total_amount` — sum matches individual transactions

### Previous Story Intelligence

**From Story 2.6 (Import UI — direct predecessor in implementation order):**
- 200 tests, 696 assertions, zero regressions — maintain this standard (expect ~210+ after this story)
- All commands via Sail: `sail artisan test`, `sail bun run build`
- `DashboardController` pattern: month param validation, BudgetQueryService injection, Inertia::render — replicate in CategoryController
- `Dashboard.tsx` uses `CategoryCard` without `id` prop — must add `id` and `currentMonth` props
- `CategoryTotal` type already includes `id` field — no type change needed for existing data
- File input reset pattern, `useCallback` pattern established — follow same React patterns
- `preserveState: true` used for partial reloads — NOT needed for category drill-down (full page visit)
- TypeScript types in `resources/js/types/budget.ts` — add new types there
- Import `@/types` re-exports from `budget.ts` — check the index file

**From Story 2.5 (Financial Overview Dashboard):**
- `BudgetQueryService` established as the shared data access layer — extend it, don't create a new service
- `getMonthlyData()` returns camelCase keys — follow same pattern in `getCategoryDetail()`
- Category slug included in query results — available for URL-friendly routes if needed later
- `formatAbsAmount()` utility already exists — use it in new components (not `formatAmount` which preserves sign)

**From Architecture doc:**
- Drill-down uses **full Inertia page visits** (not partial reloads) — each level is a separate page
- Category correction is `router.patch` — NOT in this story, but TransactionRow must be tappable later (Story 4.1)
- ColorHeader uses category accent color from DB — never hardcode colors
- Amount handling: integer cents through the entire pipeline, `formatAbsAmount()` at display only
- Page file naming: lowercase (`categories/show.tsx`), matches Inertia::render first argument

### Git Intelligence

Recent commits (one per story, mono-commit pattern):
- `20e8809` Story 2.6: Import UI with drag-and-drop, file picker, and confirmation dialog + review fixes
- `2d82942` Story 2.5: Financial overview dashboard with BudgetQueryService and review fixes
- `0035160` Story 2.4: AI auto-categorization engine

**Established patterns:**
- One commit per story
- All commands via Sail (`sail artisan test`, `sail bun run build`)
- Tests must pass with zero regressions before committing
- Feature tests use `RefreshDatabase` + seed required data
- Test file placement: `tests/Feature/` for HTTP and DB-dependent tests

### Architecture Compliance

| Requirement | Implementation |
|-------------|----------------|
| Inertia for web UI [arch#API Patterns] | `CategoryController` returns `Inertia::render()` — never JSON |
| Drill-down as full page visits [arch#Frontend Architecture] | `Link` component navigates to `/categories/{id}` — distinct page, not partial reload |
| Inertia props in camelCase [arch#Naming Patterns] | `totalAmountCents`, `transactionCount`, `currentMonth` — all camelCase |
| Services are stateless [arch#Service Boundary] | `CategoryController` delegates to `BudgetQueryService` — no business logic in controller |
| `BudgetQueryService` shared layer [arch#Service Boundary] | New `getCategoryDetail()` method added to existing service — not a new service |
| Route model binding [Laravel convention] | `show(Category $category)` — auto-resolves and 404s |
| Amount as integer cents [arch#Format Patterns] | `amountCents` throughout pipeline, `formatAbsAmount()` at display only |
| Category colors from DB [arch#Enforcement] | `category.color` passed as Inertia prop, used in `style={{ backgroundColor: color }}` |
| Naming [arch#Naming Patterns] | Controller: `CategoryController`, Route: `/categories/{category}`, Page: `categories/show` |
| 44px touch targets [ux#Accessibility] | `min-h-[44px]` on TransactionRow, SubcategoryRow padding ensures > 44px |
| Dark theme tokens [ux#Visual Foundation] | `bg-surface`, `text-white`, `text-text-secondary`, `text-text-tertiary` — all existing tokens |

### Existing Components Used (No Modifications Needed)

| Component | File | Usage |
|-----------|------|-------|
| AppLayout | `resources/js/layouts/app-layout.tsx` | Wraps Categories/Show page |
| formatAbsAmount | `resources/js/lib/formatAmount.ts` | Amount display in ColorHeader, SubcategoryRow, TransactionRow |

### What This Story Does NOT Include

- **MonthStrip on drill-down** — Story 3.2. The drill-down uses the month from the Dashboard URL param but has no month navigation of its own.
- **Account filtering** — Story 3.3. No account picker or filtering on the drill-down view.
- **Transaction tappability / correction** — Story 4.1. TransactionRow is display-only, not clickable.
- **AI learning toast** — Story 4.2. No correction feedback.
- **Import deletion** — Story 4.3. Not related to this story.
- **Subcategory creation UI** — No subcategories exist in the seeder. The code handles them if they're added later (via future stories or manual DB insertion).

### Project Structure Notes

**New files to create:**
```
app/
├── Http/Controllers/
│   └── CategoryController.php                  (NEW)
resources/js/
├── components/
│   ├── ColorHeader.tsx                         (NEW)
│   ├── SubcategoryRow.tsx                      (NEW)
│   └── TransactionRow.tsx                      (NEW)
├── lib/
│   └── formatDate.ts                           (NEW)
├── pages/
│   └── categories/
│       └── show.tsx                            (NEW)
tests/
├── Feature/
│   └── CategoryControllerTest.php              (NEW)
```

**Modified files:**
```
app/Services/BudgetQueryService.php             (add getCategoryDetail method)
routes/web.php                                  (add category show route)
resources/js/components/CategoryCard.tsx         (add Link wrapper, id+currentMonth props)
resources/js/pages/dashboard.tsx                (pass id+currentMonth to CategoryCard)
resources/js/types/budget.ts                    (add TransactionItem, SubcategoryTotal, CategoryShowProps types)
```

**Aligns with architecture directory structure:**
- Controllers in `app/Http/Controllers/` (flat) [Source: architecture.md#Structure Patterns]
- Components in `resources/js/components/` (flat) [Source: architecture.md#Structure Patterns]
- Pages in `resources/js/pages/categories/` [Source: architecture.md#Structure Patterns]
- Types in `resources/js/types/` [Source: architecture.md#Structure Patterns]
- Utilities in `resources/js/lib/` [Source: architecture.md#Structure Patterns]

### References

- Epic 3 story requirements: [Source: _bmad-output/planning-artifacts/epics.md#Story 3.1]
- Category drill-down UX: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Component Strategy]
- ColorHeader design: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#ColorHeader]
- TransactionRow design: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#TransactionRow]
- SubcategoryRow design: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#SubcategoryRow]
- Drill-down navigation pattern: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Navigation Patterns]
- Color takeover interaction: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Design Direction Decision]
- Architecture drill-down decision: [Source: _bmad-output/planning-artifacts/architecture.md#Frontend Architecture]
- BudgetQueryService: [Source: app/Services/BudgetQueryService.php]
- Category model: [Source: app/Models/Category.php]
- Transaction model: [Source: app/Models/Transaction.php]
- Previous story 2.6: [Source: _bmad-output/implementation-artifacts/2-6-import-user-interface-and-confirmation.md]
- Amount formatting: [Source: resources/js/lib/formatAmount.ts]
- Dashboard page: [Source: resources/js/pages/dashboard.tsx]
- CategoryCard component: [Source: resources/js/components/CategoryCard.tsx]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6 (claude-opus-4-6)

### Debug Log References

No issues encountered. All implementations followed the Dev Notes specifications exactly.

### Completion Notes List

- Task 1: Added `getCategoryDetail()` to BudgetQueryService — queries parent + subcategory transactions, returns structured array with subcategories, transactions, totalAmountCents, transactionCount
- Task 2: Created CategoryController with `show()` method — route model binding, month validation (matching DashboardController pattern), BudgetQueryService injection, camelCase Inertia props
- Task 3: Added `/categories/{category}` route with auth+verified middleware, named `categories.show`
- Task 4: Added TransactionItem, SubcategoryTotal, CategoryShowProps types to budget.ts (auto-exported via index.ts)
- Task 5: Created formatDate utility using Intl.DateTimeFormat — outputs "Feb 19" format, timezone-safe with T00:00:00 appending
- Task 6: Created ColorHeader component — full-width color flood with negative margins, 64px icon square, centered layout, back button, luminance-based text color switching for contrast on dark accent colors
- Task 7: Created SubcategoryRow component — tappable button with name, amount, chevron, hover state, isActive prop with visual indicator (elevated background + rotated chevron)
- Task 8: Created TransactionRow component — 48px date column, truncating description, right-aligned amount, 44px min height
- Task 9: Created Categories/Show page — ColorHeader + subcategory card + transaction list, client-side subcategory filtering via activeSubcategoryId toggle, back button with history.back() + router.visit('/dashboard') fallback for direct navigation
- Task 10: Made CategoryCard clickable — wrapped with Inertia Link to `/categories/{id}?month={currentMonth}`, added hover:bg-surface-elevated, passed id+currentMonth from Dashboard
- Task 11: 9 feature tests covering: props structure, month filtering, subcategory data (with categoryId assertions for client-side filtering), empty state, auth redirect, 404, invalid month fallback, transaction date-descending ordering, correct total amount. All 209 tests pass (828 assertions), zero regressions. TypeScript compiles cleanly.

### File List

New files:
- app/Http/Controllers/CategoryController.php
- resources/js/components/ColorHeader.tsx
- resources/js/components/SubcategoryRow.tsx
- resources/js/components/TransactionRow.tsx
- resources/js/lib/formatDate.ts
- resources/js/pages/categories/show.tsx
- tests/Feature/CategoryControllerTest.php

Modified files:
- app/Services/BudgetQueryService.php (added getCategoryDetail method + Category import)
- routes/web.php (added CategoryController import + categories.show route)
- resources/js/types/budget.ts (added TransactionItem, SubcategoryTotal, CategoryShowProps types)
- resources/js/components/CategoryCard.tsx (wrapped with Inertia Link, added id+currentMonth props, hover state)
- resources/js/pages/dashboard.tsx (pass id+currentMonth to CategoryCard)

### Change Log

- 2026-02-20: Story 3.1 implementation — Category drill-down with color takeover. Added CategoryController, BudgetQueryService.getCategoryDetail(), ColorHeader/SubcategoryRow/TransactionRow components, Categories/Show page, made CategoryCard clickable with Inertia Link navigation. 8 new feature tests, 208 total tests passing.
- 2026-02-20: Code review fixes (5 issues resolved) — [HIGH] Added isActive visual state to SubcategoryRow (elevated bg + rotated chevron); [MEDIUM] ColorHeader luminance-based text color for dark accent backgrounds; [MEDIUM] Added categoryId assertions in subcategory test for client-side filter verification; [MEDIUM] Back button falls back to router.visit('/dashboard') when no in-app history; [MEDIUM] Added transaction date-descending ordering test. 209 tests pass (828 assertions).
