---
title: 'Monthly Average Spending Indicators'
slug: 'monthly-average-indicators'
created: '2026-02-22'
status: 'completed'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['Laravel 12', 'React 19', 'Inertia.js 2', 'TypeScript', 'Tailwind CSS 4', 'PostgreSQL']
files_to_modify:
  - 'app/Services/BudgetQueryService.php'
  - 'app/Http/Controllers/DashboardController.php'
  - 'resources/js/pages/dashboard.tsx'
  - 'resources/js/components/SummaryWidget.tsx'
  - 'resources/js/components/CategoryCard.tsx'
  - 'resources/js/types/budget.ts'
  - 'tests/Feature/BudgetQueryServiceTest.php'
  - 'tests/Feature/DashboardTest.php'
code_patterns:
  - 'Integer cents for all monetary values (amount_cents in DB, amountCents in TS)'
  - 'COALESCE(parent_cat.field, categories.field) for subcategory rollup'
  - 'Inertia::defer() for non-blocking secondary data'
  - 'Account type filtering via join on accounts table'
  - 'Glassmorphic UI: bg-surface/60 backdrop-blur-2xl border-white/[0.06]'
  - 'AnimatedNumber component for smooth value transitions'
  - 'formatAmount() / formatAbsAmount() for EUR locale display'
test_patterns:
  - 'PHPUnit feature tests with CategorySeeder for standard categories'
  - 'Account factories: .personal() and .professional()'
  - 'Inertia loadDeferredProps() for testing deferred prop groups'
  - 'Vitest + @testing-library/react for component tests'
---

# Tech-Spec: Monthly Average Spending Indicators

**Created:** 2026-02-22

## Overview

### Problem Statement

The dashboard shows monthly totals but lacks context — you can't tell if a month's spending is high, low, or normal without mentally comparing to previous months.

### Solution

Add average monthly indicators (expenses, income, per-category) to the existing dashboard, calculated from all complete months of data. Replace the existing month-over-month trend percentage with a more stable average baseline + delta.

### Scope

**In Scope:**
- Average monthly expenses indicator on the summary widget (amount + delta)
- Average monthly income indicator on the summary widget (amount + delta)
- Per-category monthly average + delta on each category card (replaces current trend %)
- Delta with directional arrow and color coding (green = favorable, red = unfavorable)
- Calculation excludes the current (incomplete) month
- Works with existing account switcher (personal/professional)
- Remove existing trend percentage (vs previous month) from category cards

**Out of Scope:**
- Dedicated overview page
- Charts or sparklines
- Configurable time range (e.g., "last 6 months" / "this year" toggle)
- Per-subcategory averages

## Context for Development

### Codebase Patterns

- Monetary values stored as integer cents (`amount_cents` in DB, `amountCents` in TypeScript)
- Display formatting uses `formatAmount()` / `formatAbsAmount()` which renders French locale EUR (e.g., `1 500,00 €`)
- `BudgetQueryService` is the central query layer — all dashboard data flows through it
- Subcategory rollup pattern: `COALESCE(parent_cat.slug, categories.slug)` used in all category queries
- `DashboardController` passes props to React via Inertia; deferred props used for non-critical data (trend data currently in `'trends'` group)
- Account filtering via join: `->join('accounts', ...)->where('accounts.type', $accountType)`
- `AnimatedNumber` component handles smooth number transitions with reduced-motion support
- Glassmorphic card design: `bg-surface/60 backdrop-blur-2xl border border-white/[0.06]`

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `app/Services/BudgetQueryService.php` | Core query service — add `getMonthlyAverages()` method |
| `app/Http/Controllers/DashboardController.php` | Replace `computeTrendData()` with average computation, update deferred prop |
| `resources/js/pages/dashboard.tsx` | Update prop consumption: replace trendData with averageData |
| `resources/js/components/SummaryWidget.tsx` | Add average + delta display below income/expenses totals |
| `resources/js/components/CategoryCard.tsx` | Replace TrendIndicator with average amount + delta |
| `resources/js/types/budget.ts` | Replace `TrendData` type with `AverageData`, update `CategoryTotal` (remove `trendPercentage`) |
| `resources/js/components/AnimatedNumber.tsx` | Existing component — reuse for average display |
| `resources/js/lib/formatAmount.ts` | Existing utilities — reuse `formatAmount()` and `formatAbsAmount()` |
| `tests/Feature/BudgetQueryServiceTest.php` | Add tests for `getMonthlyAverages()` |
| `tests/Feature/DashboardTest.php` | Rewrite trend tests → average tests (12 existing trend tests to replace) |

### Technical Decisions

- Average calculated as lifetime average excluding current (incomplete) month
- Current month = `TO_CHAR(NOW(), 'YYYY-MM')` in PostgreSQL
- **Replaces** existing trend percentage (vs previous month) — average is a more stable, meaningful reference than single-month comparison
- `computeTrendData()` method in controller replaced with `computeAverageData()`
- Delta displayed as absolute amount with directional arrow (↑/↓) and color (green for favorable, red for unfavorable)
- Visual hierarchy: primary monthly amount (large, bold) → average + delta (smaller, muted, below)
- Delta color logic: for expenses, spending less than avg = green ↓; for income, earning more than avg = green ↑
- Average hidden when fewer than 2 complete months of data
- Averages served as deferred prop (same pattern as current trend data) — doesn't block initial render
- Delta computed client-side from average and current month values (keeps backend simple)

## Implementation Plan

### Tasks

- [x] **Task 1: Add `getMonthlyAverages()` to BudgetQueryService**
  - File: `app/Services/BudgetQueryService.php`
  - Action: Add new public method `getMonthlyAverages(?string $accountType = null): array`
  - Query logic:
    - Select all transactions excluding current month: `WHERE TO_CHAR(date, 'YYYY-MM') != TO_CHAR(NOW(), 'YYYY-MM')`
    - Count distinct months: `COUNT(DISTINCT TO_CHAR(date, 'YYYY-MM'))`
    - Per-category: `SUM(amount_cents) / COUNT(DISTINCT TO_CHAR(date, 'YYYY-MM'))` grouped by parent category (using existing COALESCE rollup pattern)
    - Overall: sum income categories / month count for `avgIncomeCents`, sum expense categories / month count for `avgExpensesCents`
    - Apply account type filter if provided (same join pattern as `getMonthlyData()`)
  - Return structure:
    ```php
    [
        'avgIncomeCents' => int,
        'avgExpensesCents' => int,
        'categoryAverages' => [
            'food' => ['avgAmountCents' => int],
            'transport' => ['avgAmountCents' => int],
            ...
        ],
        'completeMonthCount' => int,  // number of months used in calculation
    ]
    ```
  - Notes: Return empty/null values when `completeMonthCount < 2`. Use integer division (round) for cent averages.

- [x] **Task 2: Replace trend computation in DashboardController**
  - File: `app/Http/Controllers/DashboardController.php`
  - Action:
    - Replace `computeTrendData()` method with `computeAverageData(string $accountType): ?array`
    - New method calls `$this->budgetQueryService->getMonthlyAverages($accountType)`
    - Returns `null` if `completeMonthCount < 2`
    - Update the deferred prop: change `'trendData' => Inertia::defer(fn () => $this->computeTrendData(...), 'trends')` to `'averageData' => Inertia::defer(fn () => $this->computeAverageData($accountParam), 'averages')`
    - Remove `computeTrendData()` and `calculateTrendPercentage()` private methods
    - Remove `trendPercentage` from the `categoryTotals` mapping (currently set per category)
  - Notes: Keep the deferred prop group name change (`'trends'` → `'averages'`) so frontend can update the `only` option in Inertia requests accordingly.

- [x] **Task 3: Update TypeScript types**
  - File: `resources/js/types/budget.ts`
  - Action:
    - Remove `trendPercentage` field from `CategoryTotal` type
    - Remove `TrendData` type
    - Add new `AverageData` type:
      ```typescript
      export type AverageData = {
          avgIncomeCents: number;
          avgExpensesCents: number;
          categoryAverages: Record<string, { avgAmountCents: number }>;
          completeMonthCount: number;
      };
      ```
    - Update `DashboardProps`: replace `trendData?: TrendData` with `averageData?: AverageData`

- [x] **Task 4: Update SummaryWidget with average + delta**
  - File: `resources/js/components/SummaryWidget.tsx`
  - Action:
    - Add props: `avgIncomeCents?: number`, `avgExpensesCents?: number`
    - Below the income amount, add secondary line: `Avg: {formatAmount(avgIncomeCents)}` in `text-xs text-muted-foreground`
    - Compute income delta: `deltaCents = incomeCents - avgIncomeCents`
    - Display delta with arrow: `↑ {formatAbsAmount(deltaCents)}` (green if positive = earning more) or `↓ {formatAbsAmount(deltaCents)}` (red if negative = earning less)
    - Same pattern for expenses column but inverted colors: `↓ {formatAbsAmount(deltaCents)}` (green if spending less than avg), `↑ {formatAbsAmount(deltaCents)}` (red if spending more)
    - Hide average line when props are undefined (deferred not loaded yet, or < 2 months data)
    - Use `text-xs` size, `tabular-nums` for alignment
  - Notes: Delta is absolute amount not percentage. Use `formatAbsAmount()` for the delta value (always positive display, arrow indicates direction).

- [x] **Task 5: Update CategoryCard with average + delta**
  - File: `resources/js/components/CategoryCard.tsx`
  - Action:
    - Replace `trendPercentage?: number | null` prop with `avgAmountCents?: number`
    - Remove the existing TrendIndicator inline component (lines 17-28)
    - Add new secondary line below the category amount:
      - `Avg: {formatAmount(avgAmountCents)}` in `text-xs text-muted-foreground`
      - Delta: `deltaCents = amountCents - avgAmountCents` (both are signed negative for expenses)
      - For expense categories (amountCents < 0): spending less (|current| < |avg|) = green ↓, spending more = red ↑
      - For income category (amountCents > 0): earning more = green ↑, earning less = red ↓
      - Display: arrow + `formatAbsAmount(deltaCents)`
    - Hide when `avgAmountCents` is undefined
  - Notes: Both `amountCents` and `avgAmountCents` are signed (negative for expenses). Delta direction determined by comparing absolute values. Color: green = favorable (less expense or more income), red = unfavorable.

- [x] **Task 6: Update dashboard page prop wiring**
  - File: `resources/js/pages/dashboard.tsx`
  - Action:
    - Replace `trendData` prop usage with `averageData`
    - Update the `only` array in `router.get()` calls (month/account change): replace `'trendData'` with `'averageData'` if present
    - Pass `avgAmountCents` to each CategoryCard from `averageData?.categoryAverages[cat.slug]?.avgAmountCents`
    - Pass `avgIncomeCents` and `avgExpensesCents` to SummaryWidget from `averageData`
    - Remove all `trendPercentage` / `trendData` references
  - Notes: When `averageData` is undefined (deferred not yet loaded), cards simply don't show averages — same UX as current trend loading behavior.

- [x] **Task 7: Update backend tests**
  - File: `tests/Feature/BudgetQueryServiceTest.php`
  - Action: Add test cases for `getMonthlyAverages()`:
    - `test_monthly_averages_returns_correct_structure` — verify keys and types
    - `test_monthly_averages_calculates_correct_values` — seed 3 months of data, verify averages = total / 3
    - `test_monthly_averages_excludes_current_month` — seed current month transactions, verify they're excluded
    - `test_monthly_averages_filters_by_account_type` — seed personal + professional, verify isolation
    - `test_monthly_averages_returns_empty_when_insufficient_data` — seed only current month, verify completeMonthCount < 2 behavior
    - `test_monthly_averages_rolls_up_subcategories` — verify parent category aggregation
  - File: `tests/Feature/DashboardTest.php`
  - Action:
    - Remove existing trend percentage tests (12 tests in the "Trend Percentages" section, lines ~568-938)
    - Add average data tests:
      - `test_average_data_deferred_prop_returns_correct_structure` — using `loadDeferredProps('averages', ...)`
      - `test_average_data_includes_per_category_averages` — verify categoryAverages keyed by slug
      - `test_average_data_excludes_current_month` — seed current + past months, verify only past included
      - `test_average_data_respects_account_filter` — verify account isolation
      - `test_average_data_null_when_fewer_than_two_months` — verify null/absent when < 2 months
      - `test_category_totals_no_longer_include_trend_percentage` — verify field removed from response
  - Notes: Follow existing test patterns — use CategorySeeder, account factories, `loadDeferredProps()` for deferred assertions.

### Acceptance Criteria

- [x] **AC 1:** Given a dashboard with 3+ complete months of data, when the page loads and deferred props resolve, then the SummaryWidget displays average income and average expenses below the monthly totals, each with a delta amount and directional arrow.

- [x] **AC 2:** Given a dashboard with 3+ complete months of data, when viewing a category card, then the card displays the average monthly amount and delta instead of the previous month-over-month trend percentage.

- [x] **AC 3:** Given expenses of -1 200 € this month and an average of -1 450 €, when viewing the expenses summary, then the delta shows "↓ 250,00 €" in green (spending less than average is favorable).

- [x] **AC 4:** Given expenses of -1 600 € this month and an average of -1 450 €, when viewing the expenses summary, then the delta shows "↑ 150,00 €" in red (spending more than average is unfavorable).

- [x] **AC 5:** Given income of 3 000 € this month and an average of 2 800 €, when viewing the income summary, then the delta shows "↑ 200,00 €" in green (earning more than average is favorable).

- [x] **AC 6:** Given the user switches between personal and professional accounts, when viewing averages, then the averages are recalculated using only transactions from the selected account type.

- [x] **AC 7:** Given fewer than 2 complete months of data, when the page loads, then no average indicators are shown (averages hidden, not zero).

- [x] **AC 8:** Given the current month has transactions, when calculating averages, then the current (incomplete) month is excluded from the average calculation.

- [x] **AC 9:** Given a category that exists in the current month but not in historical data, when viewing that category card, then no average is shown for that category (graceful absence, not zero).

- [x] **AC 10:** Given the deferred prop has not yet loaded, when the page initially renders, then the dashboard shows monthly totals immediately without averages, and averages appear once the deferred prop resolves.

## Additional Context

### Dependencies

- No new packages or dependencies required
- Uses existing PostgreSQL aggregation functions (`SUM`, `COUNT(DISTINCT ...)`, `TO_CHAR`)
- Reuses existing `formatAmount()`, `AnimatedNumber`, and glassmorphic design patterns
- `getMonthlyComparison()` method in BudgetQueryService is preserved (used by MCP tools) — only controller usage of trend computation is removed

### Testing Strategy

**Backend (PHPUnit):**
- 6 new tests for `BudgetQueryService::getMonthlyAverages()` covering: structure, calculation correctness, current month exclusion, account filtering, insufficient data, subcategory rollup
- 6 replacement tests in DashboardTest for average deferred props (replacing 12 existing trend tests)
- Run: `sail artisan test`

**Frontend (Vitest):**
- Update existing component tests if any reference trendPercentage
- No new component test files required (SummaryWidget and CategoryCard changes are display-only)
- Run: `sail bun run test`

**Manual Testing:**
- Navigate between months — verify averages update correctly
- Switch accounts — verify averages recalculate
- Check with production data (12 months) — verify numbers make sense
- Check current month (Feb 2026, partial) — verify it's excluded from averages
- Verify color coding: green for favorable deltas, red for unfavorable

**Type Checking:**
- Run `sail bun run types` — verify no TypeScript errors after type changes

### Notes

- Currently 12 full months of data (Jan–Dec 2025), all with 100+ transactions
- `BudgetQueryService::getAccountSummary()` already supports all-time queries (null year/month) — validates the pattern
- Net change: removing `computeTrendData()` + `calculateTrendPercentage()` from controller, adding `getMonthlyAverages()` to service — fewer moving parts overall
- `getMonthlyComparison()` method in BudgetQueryService stays (used by MCP tools) — only controller usage removed
- Risk: rounding errors in integer division for averages — mitigated by using `(int) round($sum / $count)` consistently
- Future consideration: "last 12 months" or "this year" toggle could be added later by parameterizing the date filter in `getMonthlyAverages()`

## Review Notes
- Adversarial review completed
- Findings: 4 total, 3 fixed, 1 skipped (noise)
- Resolution approach: auto-fix
- F1 (Critical, fixed): CategoryCard arrow direction inverted for expense categories
- F2 (Low, fixed): Removed unused month_count SELECT column from query
- F3 (Low, fixed): Added missing AC 9 test for category absent from history
- F4 (Low, skipped): Two DB queries in getMonthlyAverages — acceptable for deferred prop
