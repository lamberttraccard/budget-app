# Summary Card Sparklines Design

**Date:** 2026-02-23
**Feature:** Add background sparklines to the dashboard income/expenses summary cards

## Overview

Add ambient sparklines behind the income and expenses summary cards on the dashboard. Each sparkline shows the monthly trend for the respective metric (income or expenses) over the last 12 months (or all available months if fewer). The sparklines are purely visual with no interaction — they provide at-a-glance trend awareness without adding UI complexity.

## Decisions

| Decision | Choice |
|----------|--------|
| Data shown | Both income and expenses (one sparkline per card) |
| Interaction | Purely visual, no tap targets |
| Month range | All available months, capped at 12 |
| Placement | Background behind card text (absolutely positioned) |
| Architecture | Separate `MiniSparkline` component |
| Curve type | Catmull-Rom smooth curves (matching existing `CategoryMonthlyChart`) |
| Data source | New deferred Inertia prop via `BudgetQueryService` |

## Data Flow

### Backend

Add `getMonthlyExpenseAndIncomeTotals(string $accountType): array` to `BudgetQueryService`.

Queries total income and total expenses per month, grouped by `TO_CHAR(date, 'YYYY-MM')`, filtered by account type. Returns up to 12 most recent months.

Return shape:
```php
[
    'monthlyIncomeTotals'  => [['month' => '2025-10', 'amountCents' => 148492], ...],
    'monthlyExpenseTotals' => [['month' => '2025-10', 'amountCents' => 59530], ...],
]
```

### Controller

In `DashboardController::index()`, pass as a deferred prop in the same group as averages:

```php
'sparklineData' => Inertia::defer(
    fn () => $this->budgetQueryService->getMonthlyExpenseAndIncomeTotals($accountParam),
    'averages'
),
```

### Frontend Types

Add to `resources/js/types/budget.ts`:
```ts
export type SparklineData = {
    monthlyIncomeTotals: MonthlyTotal[];
    monthlyExpenseTotals: MonthlyTotal[];
};
```

Reuses existing `MonthlyTotal` type.

## Component Design

### MiniSparkline

**File:** `resources/js/components/MiniSparkline.tsx`

**Props:**
```ts
type Props = {
    values: number[];  // array of amounts (absolute values)
    color: string;     // e.g. "#10B981" for income, "#FB7185" for expenses
};
```

**Rendering:**
- SVG with `viewBox="0 0 100 32"`, `preserveAspectRatio="none"`, fills card width
- Absolutely positioned within the card, anchored to bottom, `pointer-events: none`
- Catmull-Rom smooth curve (same algorithm as `CategoryMonthlyChart`)
- Line stroke: `color` at ~20% opacity, 1.5px width
- Fill: `color` gradient from ~8% at curve to 0% at bottom
- No dots, labels, or axes
- Renders nothing if fewer than 2 values
- Rightmost point = current/most recent month

### SummaryWidget Integration

- Receives optional `sparklineData?: SparklineData` prop
- Each card gets `relative` + `overflow-hidden` positioning
- `MiniSparkline` placed as absolute child at `bottom-0 inset-x-0`
- No skeleton needed — cards look complete without sparkline; it fades in when data arrives

## Visual Styling

- Income color: `#10B981` (existing green)
- Expenses color: `#FB7185` (existing pink)
- Stroke opacity: 20%, width: 1.5px
- Fill gradient: 8% at curve, fading to 0% at bottom
- Animation: fade in over 300ms via `motion/react`, respects `useReducedMotion`
- SVG uses `preserveAspectRatio="none"` for responsive stretching

## Edge Cases

- **< 2 months of data:** No sparkline rendered
- **Current month with 0 transactions:** Included as 0 value to avoid misleading trends
- **All values identical:** Flat horizontal line (signals stability)
- **Deferred loading:** Sparkline simply doesn't appear until data loads; no layout shift since it's positioned absolutely behind existing content
