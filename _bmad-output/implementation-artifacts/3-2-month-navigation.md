# Story 3.2: Month Navigation

Status: done

<!-- Note: Validation is optional. Run validate-create-story for quality check before dev-story. -->

## Story

As Lambert,
I want to scroll through months to view spending for different time periods,
so that I can compare my spending across months and track changes over time.

## Acceptance Criteria

1. **Given** I am on the Dashboard, **When** I look at the top of the page below the header, **Then** I see a MonthStrip: horizontally scrollable month pills with the current month highlighted (elevated surface background, white text) (FR16).

2. **Given** the MonthStrip is visible, **When** I scroll horizontally to a different month, **Then** the selected month becomes active and all Dashboard data updates instantly: category card amounts, SummaryWidget totals, and transaction counts (FR16, NFR3).

3. **Given** I navigate to a different month, **When** the data refreshes, **Then** it uses an Inertia partial reload (`router.get` with `preserveState: true`) so the update feels instant with no full page load.

4. **Given** I select a month with no imported transactions, **When** the Dashboard renders, **Then** the category list area shows "No transactions for [Month Year]" in tertiary text, and the SummaryWidget shows dashes for both Income and Expenses.

5. **Given** I open the app fresh, **When** the Dashboard loads, **Then** the MonthStrip auto-scrolls to center on the current month (FR17).

## Tasks / Subtasks

- [x] Task 1: Add `getAvailableMonths()` to BudgetQueryService (AC: 1, 5)
  - [x] 1.1: Add method that queries distinct `YYYY-MM` months from transactions table, ordered ascending
  - [x] 1.2: Return `string[]` of month strings (e.g., `['2026-01', '2026-02']`)

- [x] Task 2: Update DashboardController to return `availableMonths` prop (AC: 1, 2, 3)
  - [x] 2.1: Call `BudgetQueryService::getAvailableMonths()` and pass as `availableMonths` Inertia prop
  - [x] 2.2: Ensure `availableMonths` is NOT included in partial reload `only` list (it doesn't change on month switch)

- [x] Task 3: Update DashboardProps type (AC: 1)
  - [x] 3.1: Add `availableMonths: string[]` to `DashboardProps` in `resources/js/types/budget.ts`

- [x] Task 4: Create MonthStrip component (AC: 1, 2, 5)
  - [x] 4.1: Create `resources/js/components/MonthStrip.tsx`
  - [x] 4.2: Props: `{ currentMonth: string, availableMonths: string[], onMonthChange: (month: string) => void }`
  - [x] 4.3: Generate contiguous month range from earliest `availableMonths` entry to current calendar month
  - [x] 4.4: Render horizontally scrollable container with month pills (e.g., "Feb 26")
  - [x] 4.5: Active pill: `bg-surface-elevated text-white font-medium`; inactive: `text-text-secondary`
  - [x] 4.6: Use CSS scroll-snap (`snap-x snap-mandatory` on container, `snap-center` on pills)
  - [x] 4.7: Auto-scroll to center active month pill on mount via `useEffect` + `scrollIntoView({ inline: 'center' })`
  - [x] 4.8: Full-width with negative margins (`-mx-4 sm:-mx-8`) to break out of content padding, like ColorHeader

- [x] Task 5: Integrate MonthStrip into Dashboard (AC: 1, 2, 3, 4, 5)
  - [x] 5.1: Add `availableMonths` to destructured props
  - [x] 5.2: Add `handleMonthChange` callback using `router.get('/dashboard', { month }, { preserveState: true, preserveScroll: true, only: ['categoryTotals', 'incomeCents', 'expensesCents', 'currentMonth'] })`
  - [x] 5.3: Render MonthStrip between error display and SummaryWidget, only when `availableMonths.length > 0`
  - [x] 5.4: Replace the existing empty-state logic: distinguish "no data ever" (EmptyState) from "no data this month" (tertiary text message)
  - [x] 5.5: The existing `formatMonthLabel()` function (currently defined but unused) should be used for the empty-month message

- [x] Task 6: Update SummaryWidget to handle empty months (AC: 4)
  - [x] 6.1: Add optional `showDashes?: boolean` prop to SummaryWidget
  - [x] 6.2: When `showDashes` is true, display "—" instead of formatted amounts
  - [x] 6.3: Dashboard passes `showDashes={availableMonths.length > 0 && categoryTotals.length === 0}`

- [x] Task 7: Write Feature Tests (AC: 1-5)
  - [x] 7.1: Test DashboardController returns `availableMonths` prop with correct months
  - [x] 7.2: Test `availableMonths` is empty array when no transactions exist
  - [x] 7.3: Test `availableMonths` returns only months with transactions, ordered ascending
  - [x] 7.4: Test month query parameter changes category totals (already partially tested, verify with month filtering)
  - [x] 7.5: Test month with no transactions returns empty categoryTotals and zero income/expenses
  - [x] 7.6: Run `sail artisan test` -- all existing + new tests pass with zero regressions
  - [x] 7.7: Verify TypeScript compiles cleanly: `sail bun run build`

## Dev Notes

### Architecture Overview for This Story

This story adds temporal navigation to the Dashboard -- the MonthStrip allows scrolling through months to compare spending across time periods. It's the second story in Epic 3 (Spending Exploration) and introduces Inertia partial reloads on the Dashboard for the first time.

The backend work is minimal: one new query method on BudgetQueryService + one additional prop from DashboardController. The frontend work is the core: a new MonthStrip component, Dashboard integration with Inertia partial reloads, and empty-month state handling.

```
Dashboard (existing)
  │
  ├─ MonthStrip (NEW) -- horizontally scrollable month pills
  │     ├─ Active month pill (elevated surface, white text)
  │     └─ Inactive month pills (secondary text)
  │
  ├─ SummaryWidget (MODIFIED) -- shows dashes for empty months
  │
  ├─ CategoryCards (existing) -- amounts change on month switch
  │
  └─ Empty month state (NEW) -- "No transactions for [Month Year]"
```

**Data flow for month navigation:**
```
User taps month pill in MonthStrip
  → onMonthChange("2026-01") callback fires
  → Dashboard calls router.get('/dashboard', { month: '2026-01' }, {
      preserveState: true,     // Keep local state (file, isDragging, etc.)
      preserveScroll: true,    // Keep scroll position
      only: ['categoryTotals', 'incomeCents', 'expensesCents', 'currentMonth']
    })
  → DashboardController processes ?month=2026-01
    → BudgetQueryService::getMonthlyData(2026, 1)
    → Returns only the 4 specified props (partial reload)
  → Inertia merges new props into existing page state
  → Dashboard re-renders: MonthStrip shows new active month,
    SummaryWidget shows new totals, CategoryCards show new amounts
```

**Key design decisions:**

- **Partial reload via Inertia `only`:** Only `categoryTotals`, `incomeCents`, `expensesCents`, and `currentMonth` are refetched on month change. `availableMonths` and `importPreview` retain their values from the previous render. This makes month switching feel instant (NFR3).

- **MonthStrip only shows when data exists:** If `availableMonths.length === 0`, the MonthStrip is hidden. This preserves the clean first-time EmptyState experience.

- **Month range generation is frontend-side:** The backend returns `availableMonths` (months with transactions). The MonthStrip generates a contiguous range from the earliest available month to the current calendar month. This avoids months with no data being invisible in the strip while keeping the range bounded.

- **Empty month vs. first-time empty:** Two distinct states:
  - `availableMonths.length === 0 && categoryTotals.length === 0` → EmptyState (import prompt)
  - `availableMonths.length > 0 && categoryTotals.length === 0` → "No transactions for [Month Year]"

- **`formatMonthLabel()` already exists:** Defined but unused in `dashboard.tsx` since Story 2.5. Returns format like "February 2026". Use it for the empty-month message.

- **Full-width MonthStrip with negative margins:** Same pattern as ColorHeader (`-mx-4 sm:-mx-8`) to break out of the AppLayout content padding, allowing the scroll to reach edge-to-edge. Internal padding re-applied so pills align with content.

### BudgetQueryService Extension

```php
/**
 * Get distinct months that have transaction data, ordered ascending.
 *
 * @return string[] Array of "YYYY-MM" strings
 */
public function getAvailableMonths(): array
{
    return Transaction::query()
        ->select(DB::raw("DISTINCT TO_CHAR(date, 'YYYY-MM') as month"))
        ->orderBy('month')
        ->pluck('month')
        ->toArray();
}
```

**Notes:**
- Uses PostgreSQL `TO_CHAR` for date formatting -- matches the `YYYY-MM` format used by `currentMonth` throughout the app.
- `DISTINCT` ensures no duplicates. `ORDER BY month` gives chronological order.
- Returns an empty array when no transactions exist.
- Lightweight query -- single pass over the transactions table with a `DISTINCT` on date.

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
        'categoryTotals'  => $data['categoryTotals'],
        'incomeCents'     => $data['incomeCents'],
        'expensesCents'   => $data['expensesCents'],
        'currentMonth'    => $monthParam,
        'availableMonths' => $this->budgetQueryService->getAvailableMonths(),
        'importPreview'   => session('importPreview'),
    ]);
}
```

**Change:** One line added -- `'availableMonths' => $this->budgetQueryService->getAvailableMonths()`.

**Partial reload behavior:** When Inertia sends a partial reload request with `only: ['categoryTotals', 'incomeCents', 'expensesCents', 'currentMonth']`, the controller evaluates ALL props but Inertia only returns the requested ones. The `availableMonths` query still runs but its result is discarded by Inertia on partial requests. This is standard Inertia behavior -- if optimization is needed later, wrap in `Inertia::lazy()`, but for a single-user app with a lightweight query, this is fine.

### MonthStrip Component

```tsx
// resources/js/components/MonthStrip.tsx
import { useEffect, useRef } from 'react';

type Props = {
    currentMonth: string;
    availableMonths: string[];
    onMonthChange: (month: string) => void;
};

function formatMonthPill(month: string): string {
    const [year, m] = month.split('-');
    const date = new Date(Number(year), Number(m) - 1);
    return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
}

function generateMonthRange(availableMonths: string[], currentMonth: string): string[] {
    if (availableMonths.length === 0) return [currentMonth];

    const earliest = availableMonths[0]; // Already sorted ascending
    const latest = currentMonth > availableMonths[availableMonths.length - 1]
        ? currentMonth
        : availableMonths[availableMonths.length - 1];

    const months: string[] = [];
    const [startYear, startMonth] = earliest.split('-').map(Number);
    const [endYear, endMonth] = latest.split('-').map(Number);

    let y = startYear;
    let m = startMonth;

    while (y < endYear || (y === endYear && m <= endMonth)) {
        months.push(`${y}-${String(m).padStart(2, '0')}`);
        m++;
        if (m > 12) {
            m = 1;
            y++;
        }
    }

    return months;
}

export default function MonthStrip({ currentMonth, availableMonths, onMonthChange }: Props) {
    const activeRef = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        if (activeRef.current) {
            activeRef.current.scrollIntoView({
                inline: 'center',
                behavior: 'instant',
            });
        }
    }, []);

    const months = generateMonthRange(availableMonths, currentMonth);

    return (
        <div className="overflow-x-auto scrollbar-none -mx-4 sm:-mx-8 px-4 sm:px-8">
            <div className="flex gap-2 snap-x snap-mandatory">
                {months.map((month) => (
                    <button
                        key={month}
                        ref={month === currentMonth ? activeRef : undefined}
                        type="button"
                        onClick={() => onMonthChange(month)}
                        className={`flex-none snap-center rounded-full px-3 py-1.5 text-sm whitespace-nowrap transition-colors ${
                            month === currentMonth
                                ? 'bg-surface-elevated text-white font-medium'
                                : 'text-text-secondary hover:text-white'
                        }`}
                    >
                        {formatMonthPill(month)}
                    </button>
                ))}
            </div>
        </div>
    );
}
```

**Implementation notes:**

- **`generateMonthRange`:** Creates a contiguous array of YYYY-MM strings from the earliest month with data to either the current calendar month or the latest month with data (whichever is later). This ensures the current month is always visible even if it has no transactions yet.

- **`formatMonthPill`:** Returns short format like "Feb 26" per UX spec. Uses `en-US` locale consistent with existing `formatMonthLabel` and `formatDate`.

- **Auto-scroll via `useEffect`:** On mount, the active pill scrolls to center. Uses `behavior: 'instant'` to avoid visible animation on page load. The `ref` is attached only to the active month's button.

- **Scroll-snap:** `snap-x snap-mandatory` on the container provides snap-to-month behavior on touch devices. `snap-center` on each pill centers the snapped item.

- **Full-width scroll area:** Negative margins (`-mx-4 sm:-mx-8`) break out of the AppLayout content padding, allowing the scrollable area to extend edge-to-edge. Inner padding (`px-4 sm:px-8`) is re-applied so the first/last pills align with other content.

- **`scrollbar-none`:** Tailwind utility to hide the scrollbar on the horizontal scroll container (uses `-webkit-scrollbar` and `scrollbar-width: none`). Check that this utility is available in the project's Tailwind config or use a custom CSS rule.

- **No overflow indicator/fade:** The UX spec doesn't mention edge fade indicators on the MonthStrip. Content outside the viewport is simply clipped. The snap behavior makes it discoverable.

### Dashboard Integration

Key changes to `resources/js/pages/dashboard.tsx`:

**1. Add `availableMonths` to destructured props:**
```tsx
export default function Dashboard({
    categoryTotals,
    incomeCents,
    expensesCents,
    currentMonth,
    availableMonths,
    importPreview,
}: DashboardProps) {
```

**2. Add month change handler:**
```tsx
const handleMonthChange = useCallback((month: string) => {
    router.get('/dashboard', { month }, {
        preserveState: true,
        preserveScroll: true,
        only: ['categoryTotals', 'incomeCents', 'expensesCents', 'currentMonth'],
    });
}, []);
```

**3. Computed state for empty month detection:**
```tsx
const hasAnyData = availableMonths.length > 0;
const isEmptyMonth = hasAnyData && categoryTotals.length === 0;
```

**4. Render MonthStrip before SummaryWidget (conditionally):**
```tsx
{/* Month navigation (only shown when data exists) */}
{hasAnyData && (
    <MonthStrip
        currentMonth={currentMonth}
        availableMonths={availableMonths}
        onMonthChange={handleMonthChange}
    />
)}

<SummaryWidget
    incomeCents={incomeCents}
    expensesCents={expensesCents}
    showDashes={isEmptyMonth}
/>
```

**5. Update empty state logic:**
```tsx
{isEmptyMonth ? (
    <div className="py-12 text-center">
        <p className="text-sm text-text-tertiary">
            No transactions for {formatMonthLabel(currentMonth)}
        </p>
    </div>
) : categoryTotals.length === 0 ? (
    <EmptyState onImportClick={triggerFileInput} disabled={isUploading} />
) : (
    <>
        <div className="space-y-3">
            {categoryTotals.map((cat: CategoryTotal) => (
                <CategoryCard
                    key={cat.id}
                    id={cat.id}
                    name={cat.name}
                    color={cat.color}
                    amountCents={cat.amountCents}
                    transactionCount={cat.transactionCount}
                    currentMonth={currentMonth}
                />
            ))}
        </div>

        <button type="button" onClick={triggerFileInput} disabled={isUploading} ...>
            {isUploading ? 'Uploading...' : 'Import CSV'}
        </button>
    </>
)}
```

**6. Note:** The `formatMonthLabel()` function is already defined but unused in dashboard.tsx. It now gets used for the empty-month message. No changes needed to the function itself.

### SummaryWidget Update

```tsx
// resources/js/components/SummaryWidget.tsx
import { formatAmount } from '@/lib/formatAmount';

type Props = {
    incomeCents: number;
    expensesCents: number;
    showDashes?: boolean;
};

export default function SummaryWidget({ incomeCents, expensesCents, showDashes = false }: Props) {
    return (
        <div className="flex gap-3">
            <div className="flex-1 rounded-xl bg-surface p-3.5">
                <p className="text-xs text-text-secondary mb-1">Income</p>
                <p className="text-lg font-bold tabular-nums" style={{ color: '#10B981' }}>
                    {showDashes ? '—' : formatAmount(incomeCents)}
                </p>
            </div>
            <div className="flex-1 rounded-xl bg-surface p-3.5">
                <p className="text-xs text-text-secondary mb-1">Expenses</p>
                <p className="text-lg font-bold tabular-nums" style={{ color: '#FB7185' }}>
                    {showDashes ? '—' : formatAmount(expensesCents)}
                </p>
            </div>
        </div>
    );
}
```

**Change:** Added optional `showDashes` prop. When true, renders "—" instead of formatted amounts. Defaults to `false` for backward compatibility.

### TypeScript Type Update

Add to `resources/js/types/budget.ts`:

```typescript
export type DashboardProps = {
    categoryTotals: CategoryTotal[];
    incomeCents: number;
    expensesCents: number;
    currentMonth: string;
    availableMonths: string[];  // NEW: months with transaction data, ordered ascending
    importPreview?: ImportPreview | null;
};
```

**Change:** Added `availableMonths: string[]` to existing `DashboardProps` type. No new types needed.

### Testing Strategy

**Feature tests in `tests/Feature/DashboardControllerTest.php` (extend existing):**

All tests use `RefreshDatabase` trait and seed `CategorySeeder`.

```php
// New test methods to add:

public function test_dashboard_returns_available_months_prop(): void
{
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $category = Category::where('slug', 'food')->first();

    // Create transactions in two different months
    Transaction::factory()->create([
        'category_id' => $category->id,
        'account_id' => $account->id,
        'date' => '2026-01-15',
        'amount_cents' => -5000,
    ]);
    Transaction::factory()->create([
        'category_id' => $category->id,
        'account_id' => $account->id,
        'date' => '2026-02-10',
        'amount_cents' => -3000,
    ]);

    $response = $this->actingAs($user)
        ->get('/dashboard');

    $response->assertInertia(fn ($page) => $page
        ->component('dashboard')
        ->has('availableMonths', 2)
        ->where('availableMonths.0', '2026-01')
        ->where('availableMonths.1', '2026-02')
    );
}

public function test_dashboard_returns_empty_available_months_when_no_transactions(): void
{
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/dashboard');

    $response->assertInertia(fn ($page) => $page
        ->component('dashboard')
        ->has('availableMonths', 0)
    );
}

public function test_dashboard_month_parameter_filters_data(): void
{
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $category = Category::where('slug', 'food')->first();

    // January transaction
    Transaction::factory()->create([
        'category_id' => $category->id,
        'account_id' => $account->id,
        'date' => '2026-01-15',
        'amount_cents' => -5000,
    ]);

    // February transaction
    Transaction::factory()->create([
        'category_id' => $category->id,
        'account_id' => $account->id,
        'date' => '2026-02-10',
        'amount_cents' => -3000,
    ]);

    // Request January
    $response = $this->actingAs($user)
        ->get('/dashboard?month=2026-01');

    $response->assertInertia(fn ($page) => $page
        ->where('currentMonth', '2026-01')
        ->has('categoryTotals', 1)
        ->where('categoryTotals.0.amountCents', -5000)
    );

    // Request February
    $response = $this->actingAs($user)
        ->get('/dashboard?month=2026-02');

    $response->assertInertia(fn ($page) => $page
        ->where('currentMonth', '2026-02')
        ->has('categoryTotals', 1)
        ->where('categoryTotals.0.amountCents', -3000)
    );
}

public function test_dashboard_empty_month_returns_zero_totals(): void
{
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $category = Category::where('slug', 'food')->first();

    // Only January has data
    Transaction::factory()->create([
        'category_id' => $category->id,
        'account_id' => $account->id,
        'date' => '2026-01-15',
        'amount_cents' => -5000,
    ]);

    // Request March (no data)
    $response = $this->actingAs($user)
        ->get('/dashboard?month=2026-03');

    $response->assertInertia(fn ($page) => $page
        ->where('currentMonth', '2026-03')
        ->has('categoryTotals', 0)
        ->where('incomeCents', 0)
        ->where('expensesCents', 0)
        // availableMonths still returns January
        ->has('availableMonths', 1)
        ->where('availableMonths.0', '2026-01')
    );
}
```

**Test cases summary:**
1. `test_dashboard_returns_available_months_prop` -- multiple months returned in order
2. `test_dashboard_returns_empty_available_months_when_no_transactions` -- empty array
3. `test_dashboard_month_parameter_filters_data` -- month query param changes results
4. `test_dashboard_empty_month_returns_zero_totals` -- empty month with available months still populated

### Previous Story Intelligence

**From Story 3.1 (Category Drill-Down -- direct predecessor):**
- 209 tests, 828 assertions, zero regressions -- maintain this standard (expect ~213+ after this story)
- All commands via Sail: `sail artisan test`, `sail bun run build`
- `CategoryController` accepts `?month=YYYY-MM` and forwards `currentMonth` prop -- consistent pattern
- `BudgetQueryService` injection pattern: constructor injection, stateless methods
- `formatMonthLabel()` defined but unused in dashboard.tsx -- NOW it gets used
- `DashboardController` pattern: month param validation → BudgetQueryService → Inertia::render
- `CategoryCard` already receives `currentMonth` prop for drill-down links -- MonthStrip integration means the month flows through the entire navigation chain
- Components follow negative margin pattern for full-width breakout (ColorHeader uses `-mx-4 sm:-mx-8`)
- TypeScript types in `resources/js/types/budget.ts` -- extend `DashboardProps` there
- Types re-exported via `resources/js/types/index.ts`

**From Story 2.6 (Import UI):**
- `router.post` pattern for import with `preserveState: true` -- same Inertia option pattern for `router.get` on month change
- Dialog state management via `useState` + `useCallback` -- follow same patterns
- `isUploading` state disables import button during POST -- month changes should NOT set a loading state (they should feel instant per NFR3)

**From Story 2.5 (Financial Overview Dashboard):**
- `BudgetQueryService::getMonthlyData()` already handles month filtering
- `SummaryWidget` renders income/expenses unconditionally -- needs `showDashes` prop
- `EmptyState` shown when `categoryTotals.length === 0` -- logic needs refinement for empty-month vs. first-time-empty distinction

### Git Intelligence

Recent commits (one per story, mono-commit pattern):
- `0a33353` Story 3.1: Category drill-down with color takeover + review fixes
- `20e8809` Story 2.6: Import UI with drag-and-drop, file picker, and confirmation dialog + review fixes
- `2d82942` Story 2.5: Financial overview dashboard with BudgetQueryService and review fixes

**Established patterns:**
- One commit per story
- All commands via Sail (`sail artisan test`, `sail bun run build`)
- Tests must pass with zero regressions before committing
- Feature tests use `RefreshDatabase` + seed required data
- Test file placement: `tests/Feature/` for HTTP and DB-dependent tests

### Architecture Compliance

| Requirement | Implementation |
|-------------|----------------|
| Inertia partial reloads for month/account switching [arch#Frontend Architecture] | `router.get` with `preserveState: true, only: [...]` -- partial reload, not full page visit |
| Inertia props in camelCase [arch#Naming Patterns] | `availableMonths`, `currentMonth` -- all camelCase |
| Services are stateless [arch#Service Boundary] | `getAvailableMonths()` is a pure query method on `BudgetQueryService` |
| `BudgetQueryService` shared layer [arch#Service Boundary] | New query method added to existing service -- not a new service |
| Month navigation renders without visible loading delay [NFR3] | Partial reload fetches only changed props; MonthStrip selection is instant via local state |
| Mobile-first responsive design [ux#Responsive Strategy] | MonthStrip full-width on mobile, same layout on desktop within 480px max-width |
| Snap-to-month scroll behavior [ux#MonthStrip] | CSS `scroll-snap-type: x mandatory` with `scroll-snap-align: center` |
| Dark theme tokens [ux#Visual Foundation] | `bg-surface-elevated`, `text-white`, `text-text-secondary`, `text-text-tertiary` |
| 44px touch targets [ux#Accessibility] | Month pills have `py-1.5` padding + text size ≈ 36px total height. If needed, increase padding to meet 44px |

### Existing Components Used (No Modifications Needed)

| Component | File | Usage |
|-----------|------|-------|
| AppLayout | `resources/js/layouts/app-layout.tsx` | Wraps Dashboard (unchanged) |
| CategoryCard | `resources/js/components/CategoryCard.tsx` | Already receives `currentMonth` (unchanged) |
| EmptyState | `resources/js/components/EmptyState.tsx` | First-time empty state (unchanged) |
| ImportDropZone | `resources/js/components/ImportDropZone.tsx` | Drag-and-drop overlay (unchanged) |
| formatAmount | `resources/js/lib/formatAmount.ts` | Used by SummaryWidget (unchanged) |

### What This Story Does NOT Include

- **Account switching / filtering** -- Story 3.3. No account picker or account-scoped data filtering.
- **MonthStrip on category drill-down** -- Out of scope. The drill-down page uses the `?month=` param from the Dashboard link but has no MonthStrip of its own. Adding MonthStrip to drill-down could be a follow-up enhancement.
- **Month-based data indicators (dots under months with data)** -- The UX spec lists this as "optional." The `availableMonths` data is available to enable this later, but the initial implementation only highlights the active month.
- **Transaction tappability / correction** -- Story 4.1. TransactionRow is display-only.
- **Import deletion** -- Story 4.3. Not related to this story.
- **Lazy loading of `availableMonths`** -- The query runs on every request including partial reloads. For a single-user app this is fine. Wrap in `Inertia::lazy()` if profiling shows it matters.

### Project Structure Notes

**New files to create:**
```
resources/js/
├── components/
│   └── MonthStrip.tsx                         (NEW)
```

**Modified files:**
```
app/Services/BudgetQueryService.php             (add getAvailableMonths method)
app/Http/Controllers/DashboardController.php    (add availableMonths prop)
resources/js/pages/dashboard.tsx                (integrate MonthStrip, month change handler, empty-month state)
resources/js/types/budget.ts                    (add availableMonths to DashboardProps)
resources/js/components/SummaryWidget.tsx        (add showDashes prop)
tests/Feature/DashboardControllerTest.php       (add 4 new test methods)
```

**Aligns with architecture directory structure:**
- Components in `resources/js/components/` (flat) [Source: architecture.md#Structure Patterns]
- Services in `app/Services/` (flat) [Source: architecture.md#Structure Patterns]
- Types in `resources/js/types/` [Source: architecture.md#Structure Patterns]

### References

- Epic 3 story requirements: [Source: _bmad-output/planning-artifacts/epics.md#Story 3.2]
- MonthStrip UX design: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#MonthStrip]
- Month navigation pattern: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Navigation Patterns]
- Inertia partial reload architecture: [Source: _bmad-output/planning-artifacts/architecture.md#Frontend Architecture]
- NFR3 instant month navigation: [Source: _bmad-output/planning-artifacts/epics.md#NonFunctional Requirements]
- Empty month state: [Source: _bmad-output/planning-artifacts/ux-design-specification.md#Loading & Empty States]
- BudgetQueryService: [Source: app/Services/BudgetQueryService.php]
- DashboardController: [Source: app/Http/Controllers/DashboardController.php]
- Dashboard page: [Source: resources/js/pages/dashboard.tsx]
- SummaryWidget: [Source: resources/js/components/SummaryWidget.tsx]
- Previous story 3.1: [Source: _bmad-output/implementation-artifacts/3-1-category-drill-down-with-color-takeover.md]
- Amount formatting: [Source: resources/js/lib/formatAmount.ts]

## Dev Agent Record

### Agent Model Used

Claude Opus 4.6

### Debug Log References

No issues encountered during implementation.

### Completion Notes List

- Task 1: Added `getAvailableMonths()` to `BudgetQueryService` using PostgreSQL `TO_CHAR(date, 'YYYY-MM')` with `DISTINCT` and ascending order. Returns `string[]` of `YYYY-MM` month strings.
- Task 2: Added `availableMonths` prop to `DashboardController::index()`. The prop is not included in the partial reload `only` list, so it's only fetched on full page loads.
- Task 3: Added `availableMonths: string[]` to `DashboardProps` type in `budget.ts`.
- Task 4: Created `MonthStrip.tsx` with `generateMonthRange()` (contiguous months from earliest data to current calendar month), `formatMonthPill()` (short format like "Feb 26"), CSS scroll-snap, auto-scroll to active month on mount, and full-width negative margin breakout.
- Task 5: Integrated MonthStrip into Dashboard with Inertia partial reload via `router.get` with `preserveState: true` and `only` prop filtering. Added three-way empty state logic: empty-month message (with `formatMonthLabel`), first-time EmptyState, and normal category list.
- Task 6: Added `showDashes` prop to SummaryWidget. Displays em-dash when true, formatted amounts when false.
- Task 7: Added 4 new feature tests to `DashboardTest.php`: available months prop, empty months, ascending order, and empty month with zero totals. Added `scrollbar-none` CSS utility to `app.css`. All 213 tests pass (890 assertions), TypeScript builds cleanly.

### Change Log

- 2026-02-20: Story 3.2 implementation complete. Added MonthStrip component for temporal navigation, Inertia partial reloads for instant month switching, empty-month state handling, and 4 new feature tests.
- 2026-02-20: Code review (Claude Opus 4.6). 0 HIGH, 3 MEDIUM, 4 LOW findings. All 3 MEDIUM issues fixed: (M1) MonthStrip pill padding increased from py-1.5 to py-3 for 44px touch targets, (M2) existing prop test updated to verify availableMonths, (M3) generateMonthRange now includes currentMonth in range even if it precedes available months. 4 LOW issues accepted as-is (no account scoping on getAvailableMonths, no Inertia::lazy wrapper, hard-coded URL, unnecessary -ms-overflow-style prefix).

### File List

- `app/Services/BudgetQueryService.php` (modified) -- added `getAvailableMonths()` method
- `app/Http/Controllers/DashboardController.php` (modified) -- added `availableMonths` Inertia prop
- `resources/js/types/budget.ts` (modified) -- added `availableMonths` to `DashboardProps`
- `resources/js/components/MonthStrip.tsx` (new) -- horizontally scrollable month pill navigation
- `resources/js/pages/dashboard.tsx` (modified) -- MonthStrip integration, partial reload handler, three-way empty state
- `resources/js/components/SummaryWidget.tsx` (modified) -- added `showDashes` prop
- `resources/css/app.css` (modified) -- added `scrollbar-none` utility
- `tests/Feature/DashboardTest.php` (modified) -- 4 new test methods for `availableMonths`
