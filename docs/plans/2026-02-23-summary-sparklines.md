# Summary Card Sparklines Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add ambient background sparklines to the dashboard income/expenses summary cards showing monthly trends.

**Architecture:** New `getMonthlyExpenseAndIncomeTotals()` method on `BudgetQueryService` provides per-month income/expense totals (max 12 months). Data flows as a deferred Inertia prop to the dashboard. A new `MiniSparkline` component renders a Catmull-Rom SVG curve absolutely positioned behind each summary card.

**Tech Stack:** PHP/Laravel (BudgetQueryService, DashboardController), React/TypeScript (MiniSparkline component, SummaryWidget), SVG for rendering, motion/react for fade-in animation.

**Design doc:** `docs/plans/2026-02-23-summary-sparklines-design.md`

---

### Task 1: Backend — `getMonthlyExpenseAndIncomeTotals` method

**Files:**
- Modify: `app/Services/BudgetQueryService.php` (add method after `getMonthlyAverages`, ~line 292)
- Test: `tests/Feature/BudgetQueryServiceTest.php`

**Step 1: Write the failing tests**

Add these tests at the end of the `BudgetQueryServiceTest` class:

```php
// --- Monthly Sparkline Data Tests ---

public function test_sparkline_data_returns_correct_structure()
{
    $this->seed(CategorySeeder::class);
    $account = Account::factory()->personal()->create();
    $import = Import::factory()->completed()->create();
    $food = Category::where('slug', 'food')->first();
    $income = Category::where('slug', 'income')->first();

    Transaction::factory()->create(['amount_cents' => -30000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);
    Transaction::factory()->create(['amount_cents' => 150000, 'date' => '2025-10-05', 'category_id' => $income->id, 'account_id' => $account->id, 'import_id' => $import->id]);

    $result = $this->service->getMonthlyExpenseAndIncomeTotals('personal');

    $this->assertArrayHasKey('monthlyIncomeTotals', $result);
    $this->assertArrayHasKey('monthlyExpenseTotals', $result);
    $this->assertIsArray($result['monthlyIncomeTotals']);
    $this->assertIsArray($result['monthlyExpenseTotals']);
}

public function test_sparkline_data_separates_income_and_expenses()
{
    $this->seed(CategorySeeder::class);
    $account = Account::factory()->personal()->create();
    $import = Import::factory()->completed()->create();
    $food = Category::where('slug', 'food')->first();
    $transport = Category::where('slug', 'transport')->first();
    $income = Category::where('slug', 'income')->first();

    // Oct 2025: income 150000, expenses -30000 food + -20000 transport = 50000
    Transaction::factory()->create(['amount_cents' => 150000, 'date' => '2025-10-05', 'category_id' => $income->id, 'account_id' => $account->id, 'import_id' => $import->id]);
    Transaction::factory()->create(['amount_cents' => -30000, 'date' => '2025-10-10', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);
    Transaction::factory()->create(['amount_cents' => -20000, 'date' => '2025-10-15', 'category_id' => $transport->id, 'account_id' => $account->id, 'import_id' => $import->id]);

    // Nov 2025: income 200000, expenses -40000 food
    Transaction::factory()->create(['amount_cents' => 200000, 'date' => '2025-11-05', 'category_id' => $income->id, 'account_id' => $account->id, 'import_id' => $import->id]);
    Transaction::factory()->create(['amount_cents' => -40000, 'date' => '2025-11-10', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);

    $result = $this->service->getMonthlyExpenseAndIncomeTotals('personal');

    $this->assertCount(2, $result['monthlyIncomeTotals']);
    $this->assertEquals('2025-10', $result['monthlyIncomeTotals'][0]['month']);
    $this->assertEquals(150000, $result['monthlyIncomeTotals'][0]['amountCents']);
    $this->assertEquals('2025-11', $result['monthlyIncomeTotals'][1]['month']);
    $this->assertEquals(200000, $result['monthlyIncomeTotals'][1]['amountCents']);

    $this->assertCount(2, $result['monthlyExpenseTotals']);
    $this->assertEquals('2025-10', $result['monthlyExpenseTotals'][0]['month']);
    $this->assertEquals(50000, $result['monthlyExpenseTotals'][0]['amountCents']); // absolute value
    $this->assertEquals('2025-11', $result['monthlyExpenseTotals'][1]['month']);
    $this->assertEquals(40000, $result['monthlyExpenseTotals'][1]['amountCents']);
}

public function test_sparkline_data_caps_at_12_months()
{
    $this->seed(CategorySeeder::class);
    $account = Account::factory()->personal()->create();
    $import = Import::factory()->completed()->create();
    $food = Category::where('slug', 'food')->first();

    // Create 14 months of data
    for ($i = 0; $i < 14; $i++) {
        $date = now()->subMonths(14 - $i)->startOfMonth()->addDays(14)->toDateString();
        Transaction::factory()->create(['amount_cents' => -10000, 'date' => $date, 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);
    }

    $result = $this->service->getMonthlyExpenseAndIncomeTotals('personal');

    $this->assertLessThanOrEqual(12, count($result['monthlyExpenseTotals']));
}

public function test_sparkline_data_filters_by_account_type()
{
    $this->seed(CategorySeeder::class);
    $personalAccount = Account::factory()->personal()->create();
    $professionalAccount = Account::factory()->professional()->create();
    $import = Import::factory()->completed()->create();
    $food = Category::where('slug', 'food')->first();

    Transaction::factory()->create(['amount_cents' => -10000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $personalAccount->id, 'import_id' => $import->id]);
    Transaction::factory()->create(['amount_cents' => -50000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $professionalAccount->id, 'import_id' => $import->id]);

    $personal = $this->service->getMonthlyExpenseAndIncomeTotals('personal');
    $this->assertEquals(10000, $personal['monthlyExpenseTotals'][0]['amountCents']);

    $professional = $this->service->getMonthlyExpenseAndIncomeTotals('professional');
    $this->assertEquals(50000, $professional['monthlyExpenseTotals'][0]['amountCents']);
}

public function test_sparkline_data_returns_empty_arrays_with_no_data()
{
    $result = $this->service->getMonthlyExpenseAndIncomeTotals('personal');

    $this->assertEmpty($result['monthlyIncomeTotals']);
    $this->assertEmpty($result['monthlyExpenseTotals']);
}

public function test_sparkline_data_includes_months_with_zero_for_missing_side()
{
    $this->seed(CategorySeeder::class);
    $account = Account::factory()->personal()->create();
    $import = Import::factory()->completed()->create();
    $food = Category::where('slug', 'food')->first();

    // Only expenses in Oct, no income
    Transaction::factory()->create(['amount_cents' => -30000, 'date' => '2025-10-15', 'category_id' => $food->id, 'account_id' => $account->id, 'import_id' => $import->id]);

    $result = $this->service->getMonthlyExpenseAndIncomeTotals('personal');

    $this->assertCount(1, $result['monthlyExpenseTotals']);
    $this->assertEquals(30000, $result['monthlyExpenseTotals'][0]['amountCents']);
    // Income should have an entry for Oct with 0
    $this->assertCount(1, $result['monthlyIncomeTotals']);
    $this->assertEquals(0, $result['monthlyIncomeTotals'][0]['amountCents']);
}
```

**Step 2: Run tests to verify they fail**

Run: `sail artisan test --filter=test_sparkline_data`
Expected: FAIL — method `getMonthlyExpenseAndIncomeTotals` does not exist.

**Step 3: Write the implementation**

Add this method to `app/Services/BudgetQueryService.php` after `getMonthlyAverages()` (after line 292):

```php
/**
 * Get monthly income and expense totals for sparkline display.
 * Returns the most recent 12 months of data, with income and expenses separated.
 * Expense amounts are returned as positive (absolute) values.
 *
 * @return array{monthlyIncomeTotals: array, monthlyExpenseTotals: array}
 */
public function getMonthlyExpenseAndIncomeTotals(string $accountType): array
{
    $query = Transaction::query()
        ->select([
            DB::raw("TO_CHAR(transactions.date, 'YYYY-MM') as month"),
            DB::raw("COALESCE(SUM(CASE WHEN COALESCE(parent_cat.slug, categories.slug) = 'income' THEN transactions.amount_cents ELSE 0 END), 0) as income_cents"),
            DB::raw("COALESCE(SUM(CASE WHEN COALESCE(parent_cat.slug, categories.slug) != 'income' THEN ABS(transactions.amount_cents) ELSE 0 END), 0) as expenses_cents"),
        ])
        ->join('categories', 'transactions.category_id', '=', 'categories.id')
        ->leftJoin('categories as parent_cat', 'categories.parent_id', '=', 'parent_cat.id')
        ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
        ->where('accounts.type', $accountType)
        ->groupBy(DB::raw("TO_CHAR(transactions.date, 'YYYY-MM')"))
        ->orderBy('month')
        ->limit(12);

    $rows = $query->get();

    // If more than 12, take the last 12
    if ($rows->count() > 12) {
        $rows = $rows->slice(-12)->values();
    }

    $monthlyIncomeTotals = $rows->map(fn ($row) => [
        'month' => $row->month,
        'amountCents' => (int) $row->income_cents,
    ])->values()->toArray();

    $monthlyExpenseTotals = $rows->map(fn ($row) => [
        'month' => $row->month,
        'amountCents' => (int) $row->expenses_cents,
    ])->values()->toArray();

    return compact('monthlyIncomeTotals', 'monthlyExpenseTotals');
}
```

**Step 4: Run tests to verify they pass**

Run: `sail artisan test --filter=test_sparkline_data`
Expected: All 6 new tests PASS.

**Step 5: Commit**

```bash
git add app/Services/BudgetQueryService.php tests/Feature/BudgetQueryServiceTest.php
git commit -m "feat: add getMonthlyExpenseAndIncomeTotals to BudgetQueryService"
```

---

### Task 2: Controller — Wire sparkline data as deferred prop

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php:32-42` (add prop to Inertia::render)

**Step 1: Add sparklineData to the Inertia response**

In `DashboardController::index()`, add `sparklineData` as a deferred prop. Add it in the same `'averages'` group as `averageData` so they load together. Place it right after the `'averageData'` line (~line 40):

```php
'sparklineData' => Inertia::defer(fn () => $this->budgetQueryService->getMonthlyExpenseAndIncomeTotals($accountParam), 'averages'),
```

Also add `'sparklineData'` to the `only` arrays in the dashboard's `handleMonthChange` and `handleAccountChange` callbacks (this is a frontend change noted here for completeness — done in Task 5).

**Step 2: Verify the dashboard still loads**

Run: `sail artisan test --filter=DashboardController` (if test exists) or manually verify by loading the app.
Run: `sail artisan test` to ensure no regressions.

**Step 3: Commit**

```bash
git add app/Http/Controllers/DashboardController.php
git commit -m "feat: pass sparklineData as deferred prop to dashboard"
```

---

### Task 3: Frontend types — Add SparklineData type

**Files:**
- Modify: `resources/js/types/budget.ts` (add type, update DashboardProps)

**Step 1: Add the SparklineData type and update DashboardProps**

Add after the `AverageData` type (~line 64):

```ts
export type SparklineData = {
    monthlyIncomeTotals: MonthlyTotal[];
    monthlyExpenseTotals: MonthlyTotal[];
};
```

Add to `DashboardProps` (~line 143, after `averageData`):

```ts
sparklineData?: SparklineData;
```

**Step 2: Commit**

```bash
git add resources/js/types/budget.ts
git commit -m "feat: add SparklineData type"
```

---

### Task 4: Frontend — MiniSparkline component

**Files:**
- Create: `resources/js/components/MiniSparkline.tsx`
- Create: `resources/js/components/__tests__/MiniSparkline.test.tsx`

**Step 1: Write the failing tests**

Create `resources/js/components/__tests__/MiniSparkline.test.tsx`:

```tsx
import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import MiniSparkline from '../MiniSparkline';

describe('MiniSparkline', () => {
    it('renders nothing with fewer than 2 values', () => {
        const { container } = render(
            <MiniSparkline values={[100]} color="#10B981" />,
        );
        expect(container.innerHTML).toBe('');
    });

    it('renders nothing with empty values', () => {
        const { container } = render(
            <MiniSparkline values={[]} color="#10B981" />,
        );
        expect(container.innerHTML).toBe('');
    });

    it('renders an SVG with 2+ values', () => {
        const { container } = render(
            <MiniSparkline values={[100, 200, 150]} color="#10B981" />,
        );
        const svg = container.querySelector('svg');
        expect(svg).toBeInTheDocument();
    });

    it('has pointer-events none for non-interactive behavior', () => {
        const { container } = render(
            <MiniSparkline values={[100, 200, 150]} color="#10B981" />,
        );
        const wrapper = container.firstElementChild as HTMLElement;
        expect(wrapper).toHaveClass('pointer-events-none');
    });

    it('renders a path element for the line', () => {
        const { container } = render(
            <MiniSparkline values={[100, 200, 150]} color="#10B981" />,
        );
        const paths = container.querySelectorAll('path');
        // Should have at least 2 paths: fill + line
        expect(paths.length).toBeGreaterThanOrEqual(2);
    });

    it('uses the provided color for stroke', () => {
        const { container } = render(
            <MiniSparkline values={[100, 200]} color="#FB7185" />,
        );
        const paths = container.querySelectorAll('path');
        const linePath = Array.from(paths).find((p) => p.getAttribute('stroke'));
        expect(linePath?.getAttribute('stroke')).toBe('#FB7185');
    });
});
```

**Step 2: Run tests to verify they fail**

Run: `sail bun run test -- --run resources/js/components/__tests__/MiniSparkline.test.tsx`
Expected: FAIL — cannot find `../MiniSparkline`.

**Step 3: Write the MiniSparkline component**

Create `resources/js/components/MiniSparkline.tsx`:

```tsx
import { useId } from 'react';

type Props = {
    values: number[];
    color: string;
};

const VIEW_W = 100;
const VIEW_H = 32;
const PAD_Y = 4;

export default function MiniSparkline({ values, color }: Props) {
    const gradientId = useId();

    if (values.length < 2) return null;

    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min;
    const yPad = range > 0 ? range * 0.1 : 1;
    const yMin = min - yPad;
    const yMax = max + yPad;

    const points = values.map((v, i) => ({
        x: (i / (values.length - 1)) * VIEW_W,
        y: PAD_Y + (1 - (v - yMin) / (yMax - yMin)) * (VIEW_H - PAD_Y * 2),
    }));

    // Catmull-Rom to cubic bezier
    let linePath = `M ${points[0].x},${points[0].y}`;
    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[Math.max(i - 1, 0)];
        const p1 = points[i];
        const p2 = points[i + 1];
        const p3 = points[Math.min(i + 2, points.length - 1)];
        const cp1x = p1.x + (p2.x - p0.x) / 4;
        const cp1y = p1.y + (p2.y - p0.y) / 4;
        const cp2x = p2.x - (p3.x - p1.x) / 4;
        const cp2y = p2.y - (p3.y - p1.y) / 4;
        linePath += ` C ${cp1x},${cp1y} ${cp2x},${cp2y} ${p2.x},${p2.y}`;
    }

    const fillPath = `${linePath} L ${points[points.length - 1].x},${VIEW_H} L ${points[0].x},${VIEW_H} Z`;

    return (
        <div className="pointer-events-none absolute inset-x-0 bottom-0" style={{ height: 32 }}>
            <svg
                viewBox={`0 0 ${VIEW_W} ${VIEW_H}`}
                preserveAspectRatio="none"
                className="block h-full w-full"
            >
                <defs>
                    <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor={color} stopOpacity={0.08} />
                        <stop offset="100%" stopColor={color} stopOpacity={0} />
                    </linearGradient>
                </defs>
                <path d={fillPath} fill={`url(#${gradientId})`} />
                <path
                    d={linePath}
                    fill="none"
                    stroke={color}
                    strokeWidth={1.5}
                    strokeOpacity={0.2}
                    strokeLinecap="round"
                    vectorEffect="non-scaling-stroke"
                />
            </svg>
        </div>
    );
}
```

**Step 4: Run tests to verify they pass**

Run: `sail bun run test -- --run resources/js/components/__tests__/MiniSparkline.test.tsx`
Expected: All 6 tests PASS.

**Step 5: Commit**

```bash
git add resources/js/components/MiniSparkline.tsx resources/js/components/__tests__/MiniSparkline.test.tsx
git commit -m "feat: add MiniSparkline component"
```

---

### Task 5: Frontend — Integrate sparklines into SummaryWidget and Dashboard

**Files:**
- Modify: `resources/js/components/SummaryWidget.tsx`
- Modify: `resources/js/pages/dashboard.tsx`

**Step 1: Update SummaryWidget to accept and render sparklines**

Modify `resources/js/components/SummaryWidget.tsx`:

1. Add imports at the top:
```tsx
import type { SparklineData } from '@/types';
import MiniSparkline from '@/components/MiniSparkline';
```

2. Add `sparklineData` to the Props type:
```tsx
type Props = {
    incomeCents: number;
    expensesCents: number;
    avgIncomeCents?: number;
    avgExpensesCents?: number;
    showDashes?: boolean;
    sparklineData?: SparklineData;
};
```

3. Destructure `sparklineData` in the component signature.

4. Extract the value arrays:
```tsx
const incomeValues = sparklineData?.monthlyIncomeTotals.map((d) => d.amountCents) ?? [];
const expenseValues = sparklineData?.monthlyExpenseTotals.map((d) => d.amountCents) ?? [];
```

5. Add `relative overflow-hidden` to each card's outer `div` (the `flex-1 rounded-xl` elements).

6. Add `<MiniSparkline>` as the last child inside each card:
   - Income card: `<MiniSparkline values={incomeValues} color="#10B981" />`
   - Expenses card: `<MiniSparkline values={expenseValues} color="#FB7185" />`

**Step 2: Update Dashboard to pass sparklineData**

Modify `resources/js/pages/dashboard.tsx`:

1. Update the `DashboardProps` destructuring to include `sparklineData`:
```tsx
export default function Dashboard({
    categoryTotals,
    incomeCents,
    expensesCents,
    currentMonth,
    currentAccount,
    availableMonths,
    importPreview,
    averageData,
    imports,
    sparklineData,
}: DashboardProps) {
```

2. Pass `sparklineData` to `SummaryWidget`:
```tsx
<SummaryWidget
    incomeCents={incomeCents}
    expensesCents={expensesCents}
    avgIncomeCents={averageData?.avgIncomeCents}
    avgExpensesCents={averageData?.avgExpensesCents}
    showDashes={isEmptyMonth}
    sparklineData={sparklineData}
/>
```

3. Add `'sparklineData'` to both `only` arrays in `handleMonthChange` and `handleAccountChange`:
   - `handleMonthChange` only array (~line 127): add `'sparklineData'`
   - `handleAccountChange` only array (~line 135): add `'sparklineData'`

**Step 3: Run linting and type check**

Run: `sail bun run types && sail bun run lint`
Expected: No errors.

**Step 4: Run all tests**

Run: `sail bun run test` and `sail artisan test`
Expected: All tests pass.

**Step 5: Commit**

```bash
git add resources/js/components/SummaryWidget.tsx resources/js/pages/dashboard.tsx
git commit -m "feat: integrate sparklines into dashboard summary cards"
```

---

### Task 6: Visual polish and manual testing

**Files:**
- Possibly tweak: `resources/js/components/MiniSparkline.tsx` (opacity, height)
- Possibly tweak: `resources/js/components/SummaryWidget.tsx` (z-index, padding)

**Step 1: Manual visual testing**

Start the dev server: `sail composer dev`

Check on desktop and mobile (PWA):
- [ ] Sparklines appear behind both income and expenses cards after deferred data loads
- [ ] Sparklines fade in smoothly (no layout shift)
- [ ] Text remains clearly readable over the sparkline
- [ ] Cards look normal when sparkline data isn't available yet (initial load)
- [ ] Sparkline shape looks correct for the actual data
- [ ] Switching months reloads sparkline data
- [ ] Switching account type reloads sparkline data
- [ ] With < 2 months data, no sparkline renders

**Step 2: Adjust if needed**

Typical adjustments:
- If sparkline is too prominent: reduce `strokeOpacity` from 0.2 to 0.15, fill from 0.08 to 0.05
- If sparkline height feels wrong: adjust the `height: 32` in MiniSparkline's wrapper
- If text readability suffers: add a subtle `z-10` to the text content, or reduce sparkline opacity further

**Step 3: Final commit**

```bash
git add -A
git commit -m "polish: tune sparkline visual appearance"
```

(Skip this commit if no adjustments were needed.)

---

### Summary

| Task | What | Files |
|------|------|-------|
| 1 | Backend query method + tests | BudgetQueryService.php, BudgetQueryServiceTest.php |
| 2 | Wire deferred prop in controller | DashboardController.php |
| 3 | Add TypeScript types | budget.ts |
| 4 | MiniSparkline component + tests | MiniSparkline.tsx, MiniSparkline.test.tsx |
| 5 | Integrate into SummaryWidget + Dashboard | SummaryWidget.tsx, dashboard.tsx |
| 6 | Visual polish + manual testing | Tweaks as needed |
